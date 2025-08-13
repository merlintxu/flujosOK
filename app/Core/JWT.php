<?php
/**
 * Flujos Dimension v4.1 - JWT Authentication
 * Manejo real de tokens JWT para autenticación de API
 * 
 * @version 4.1.0
 * @author Manus AI
 */
namespace FlujosDimension\Core;

use Exception;
use PDO;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
class JWT
{
    private string $currentKey;
    /** @var array<string,string> */
    private array $previousKeys;
    private string $kid;
    private string $algorithm;
    private int $expirationHours;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $config = Config::getInstance();
        $jwtConfig = $config->getJwtConfig();

        $this->currentKey   = (string)$jwtConfig['current_key'];
        $this->previousKeys = $this->parseKeyList((string)$jwtConfig['previous_keys']);
        $this->kid          = (string)$jwtConfig['kid'];
        $this->algorithm    = $jwtConfig['algorithm'];
        $this->expirationHours = (int)$jwtConfig['expiration_hours'];
        $this->pdo = $pdo;

        if (empty($this->currentKey)) {
            throw new Exception('JWT current key not configured');
        }
    }

    /**
     * Parse comma-separated list of kid:secret pairs.
     * @return array<string,string>
     */
    private function parseKeyList(string $list): array
    {
        $keys = [];
        foreach (array_filter(array_map('trim', explode(',', $list))) as $pair) {
            [$k, $v] = array_map('trim', explode(':', $pair, 2));
            if ($k !== '' && $v !== '') {
                $keys[$k] = $v;
            }
        }
        return $keys;
    }
    
    /**
     * Generar token JWT
     */
    public function generateToken($payload = [])
    {
        $now = time();
        $jti = bin2hex(random_bytes(16));
        $basePayload = [
            'jti' => $jti,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($this->expirationHours * 3600),
        ];
        $payload = array_merge($basePayload, $payload);
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm,
            'kid' => $this->kid,
        ];

        $token = FirebaseJWT::encode($payload, $this->currentKey, $this->algorithm, null, $header);
        $this->saveTokenToDatabase($jti, $payload['exp']);
        return $token;
    }
    
    /**
     * Validar token JWT
     */
    public function validateToken($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }

            $header = json_decode($this->base64UrlDecode($parts[0]), true);
            $kid = $header['kid'] ?? '';
            $key = $kid === $this->kid ? $this->currentKey : ($this->previousKeys[$kid] ?? null);
            if ($key === null) {
                return false;
            }

            $payload = (array) FirebaseJWT::decode($token, new Key($key, $this->algorithm));

            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $this->logInfo('Token expired');
                return false;
            }

            $jti = $payload['jti'] ?? null;
            if (!$jti || !$this->isTokenActiveInDatabase($jti)) {
                return false;
            }

            $this->updateTokenLastUsed($jti);
            return $payload;
        } catch (Exception $e) {
            $this->logError('Token validation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revocar token
     */
    public function revokeToken($token)
    {
        try {
            $parts = explode('.', $token);
            $header = json_decode($this->base64UrlDecode($parts[0] ?? ''), true);
            $kid = $header['kid'] ?? '';
            $key = $kid === $this->kid ? $this->currentKey : ($this->previousKeys[$kid] ?? null);
            if ($key === null) {
                return false;
            }
            $payload = (array) FirebaseJWT::decode($token, new Key($key, $this->algorithm));
            $jti = $payload['jti'] ?? null;
            if (!$jti) {
                return false;
            }
            $stmt = $this->pdo->prepare(
                "UPDATE api_tokens SET is_active = FALSE WHERE token_hash = ?"
            );
            $stmt->execute([hash('sha256', $jti)]);
            $result = $stmt->rowCount();
            if ($result > 0) {
                $this->logInfo('Token revoked successfully');
                return true;
            }
            return false;
        } catch (Exception $e) {
            $this->logError('Error revoking token: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener tokens activos
     */
    public function getActiveTokens()
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, expires_at, last_used_at, created_at
                 FROM api_tokens
                 WHERE is_active = TRUE AND expires_at > CURRENT_TIMESTAMP
                 ORDER BY created_at DESC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logError("Error getting active tokens: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpiar tokens expirados
     */
    public function cleanupExpiredTokens()
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM api_tokens WHERE expires_at < CURRENT_TIMESTAMP OR is_active = FALSE"
            );
            $stmt->execute();
            $result = $stmt->rowCount();
            
            if ($result > 0) {
                $this->logInfo("Cleaned up $result expired tokens");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError("Error cleaning up tokens: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Decodificar Base64 URL-safe
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Guardar token en base de datos
     */
    private function saveTokenToDatabase(string $jti, int $expiresAt): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO api_tokens (token_hash, name, expires_at) VALUES (?, ?, ?)"
            );
            $stmt->execute([
                hash('sha256', $jti),
                'API Access Token',
                date('Y-m-d H:i:s', $expiresAt)
            ]);
            $this->logInfo('Token saved to database successfully');
        } catch (Exception $e) {
            $this->logError('Error saving token to database: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el token está activo en la base de datos
     */
    private function isTokenActiveInDatabase(string $jti): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM api_tokens WHERE token_hash = ? AND is_active = TRUE AND expires_at > CURRENT_TIMESTAMP"
            );
            $stmt->execute([hash('sha256', $jti)]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            $this->logError('Error checking token in database: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar último uso del token
     */
    private function updateTokenLastUsed(string $jti): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE api_tokens SET last_used_at = ? WHERE token_hash = ?"
            );
            $stmt->execute([
                date('Y-m-d H:i:s'),
                hash('sha256', $jti)
            ]);
        } catch (Exception $e) {
            $this->logError('Error updating token last used: ' . $e->getMessage());
        }
    }
    
    /**
     * Registrar información en logs
     */
    private function logInfo($message)
    {
        $this->writeLog('info', $message);
    }
    
    /**
     * Registrar error en logs
     */
    private function logError($message)
    {
        $this->writeLog('error', $message);
    }
    
    /**
     * Escribir en archivo de log
     */
    private function writeLog($level, $message)
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/jwt.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>

