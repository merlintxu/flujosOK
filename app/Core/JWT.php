<?php
/**
 * Flujos Dimension v4.1 - JWT Authentication
 * Manejo real de tokens JWT para autenticación de API
 * 
 * @version 4.1.0
 * @author Manus AI
 */
namespace FlujosDimension\Core;
class JWT
{
    private $secret;
    private $algorithm;
    private $expirationHours;
    
    public function __construct()
    {
        $config = Config::getInstance();
        $jwtConfig = $config->getJwtConfig();
        
        $this->secret = $jwtConfig['secret'];
        $this->algorithm = $jwtConfig['algorithm'];
        $this->expirationHours = $jwtConfig['expiration_hours'];
        
        if (empty($this->secret)) {
            throw new Exception("JWT secret not configured");
        }
    }
    
    /**
     * Generar token JWT
     */
    public function generateToken($payload = [])
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];
        
        $now = time();
        $defaultPayload = [
            'iat' => $now, // Issued at
            'exp' => $now + ($this->expirationHours * 3600), // Expiration
            'iss' => 'flujos-dimension-v4.1', // Issuer
            'sub' => 'api-access' // Subject
        ];
        
        $payload = array_merge($defaultPayload, $payload);
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = $this->generateSignature($headerEncoded . '.' . $payloadEncoded);
        
        $token = $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
        
        // Guardar token en base de datos
        $this->saveTokenToDatabase($token, $payload['exp']);
        
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
            
            list($headerEncoded, $payloadEncoded, $signature) = $parts;
            
            // Verificar firma
            $expectedSignature = $this->generateSignature($headerEncoded . '.' . $payloadEncoded);
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            // Decodificar payload
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            
            if (!$payload) {
                return false;
            }
            
            // Verificar expiración
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $this->logInfo("Token expired for: " . ($payload['sub'] ?? 'unknown'));
                return false;
            }
            
            // Verificar que el token existe en la base de datos y está activo
            if (!$this->isTokenActiveInDatabase($token)) {
                return false;
            }
            
            // Actualizar último uso
            $this->updateTokenLastUsed($token);
            
            return $payload;
            
        } catch (Exception $e) {
            $this->logError("Token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revocar token
     */
    public function revokeToken($token)
    {
        try {
            $db = Database::getInstance();
            $tokenHash = hash('sha256', $token);
            
            $result = $db->update(
                "UPDATE api_tokens SET is_active = FALSE WHERE token_hash = ?",
                [$tokenHash]
            );
            
            if ($result > 0) {
                $this->logInfo("Token revoked successfully");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logError("Error revoking token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener tokens activos
     */
    public function getActiveTokens()
    {
        try {
            $db = Database::getInstance();
            
            return $db->select(
                "SELECT id, name, expires_at, last_used_at, created_at 
                 FROM api_tokens 
                 WHERE is_active = TRUE AND expires_at > NOW() 
                 ORDER BY created_at DESC"
            );
            
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
            $db = Database::getInstance();
            
            $result = $db->delete(
                "DELETE FROM api_tokens WHERE expires_at < NOW() OR is_active = FALSE"
            );
            
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
     * Generar firma HMAC
     */
    private function generateSignature($data)
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }
    
    /**
     * Codificar en Base64 URL-safe
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
    private function saveTokenToDatabase($token, $expiresAt)
    {
        try {
            $db = Database::getInstance();
            $tokenHash = hash('sha256', $token);
            
            $db->insert(
                "INSERT INTO api_tokens (token_hash, name, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))",
                [$tokenHash, 'API Access Token', $expiresAt]
            );
            
            $this->logInfo("Token saved to database successfully");
            
        } catch (Exception $e) {
            $this->logError("Error saving token to database: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el token está activo en la base de datos
     */
    private function isTokenActiveInDatabase($token)
    {
        try {
            $db = Database::getInstance();
            $tokenHash = hash('sha256', $token);
            
            $result = $db->selectOne(
                "SELECT id FROM api_tokens WHERE token_hash = ? AND is_active = TRUE AND expires_at > NOW()",
                [$tokenHash]
            );
            
            return $result !== false;
            
        } catch (Exception $e) {
            $this->logError("Error checking token in database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar último uso del token
     */
    private function updateTokenLastUsed($token)
    {
        try {
            $db = Database::getInstance();
            $tokenHash = hash('sha256', $token);
            
            $db->update(
                "UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?",
                [$tokenHash]
            );
            
        } catch (Exception $e) {
            $this->logError("Error updating token last used: " . $e->getMessage());
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

