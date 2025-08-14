<?php
namespace FlujosDimension\Core;

use Exception;
use PDO;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    public function __construct(private PDO $pdo) {}

    private function env(string $k, ?string $def=null): ?string {
        $v = $_ENV[$k] ?? getenv($k);
        return $v !== false ? $v : $def;
    }

    private function isRS256(): bool {
        return strtoupper($this->env('JWT_ALG','HS256')) === 'RS256';
    }

    private function currentKid(): ?string {
        return $this->env('JWT_KID') ?: null;
    }

    private function jwksPath(): ?string {
        return $this->env('JWT_JWKS_PATH') ?: null;
    }

    private function rsPrivateKey(): ?string {
        $p = $this->env('JWT_PRIVATE_KEY_PATH');
        return ($p && file_exists($p)) ? file_get_contents($p) : null;
    }

    private function hsSecret(): ?string {
        return $this->env('JWT_KEYS_CURRENT') ?: null;
    }

    private function getPublicKeyByKid(string $kid): ?string {
        $jwks = $this->jwksPath();
        if (!$jwks || !file_exists($jwks)) return null;
        $data = json_decode((string)file_get_contents($jwks), true);
        foreach (($data['keys'] ?? []) as $k) {
            if (($k['kid'] ?? '') === $kid) {
                // Convertir JWK RSA pública (n,e) a PEM
                $n = $k['n']; $e = $k['e'];
                return $this->jwkToPem($n, $e);
            }
        }
        return null;
    }

    private function jwkToPem(string $nB64u, string $eB64u): string {
        $n = $this->b64uDec($nB64u);
        $e = $this->b64uDec($eB64u);
        // Estructura mínima ASN.1 para RSA public key
        $seq = $this->asn1Sequence($this->asn1Integer($n) . $this->asn1Integer($e));
        $bitStr = "\x00" . $seq;
        $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption OID
        $spki = $this->asn1Sequence($algId . "\x03" . $this->asn1Length(strlen($bitStr)) . $bitStr);
        $pem = "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($spki), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
        return $pem;
    }
    private function b64uDec(string $s): string { return base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s)%4)%4)); }
    private function asn1Length(int $l): string { return $l<128 ? chr($l) : chr(0x80|strlen($len=pack('N',$l))) . ltrim($len, "\x00"); }
    private function asn1Integer(string $x): string { if (ord($x[0])>0x7f) $x="\x00".$x; return "\x02".$this->asn1Length(strlen($x)).$x; }
    private function asn1Sequence(string $x): string { return "\x30".$this->asn1Length(strlen($x)).$x; }

    /** Genera y registra el token (api_tokens.token_hash, expires_at si viene en payload) */
    public function generateToken(array $payload): string
    {
        $header = ['typ'=>'JWT'];
        if ($this->isRS256()) {
            $kid = $this->currentKid();
            $priv = $this->rsPrivateKey();
            if (!$kid || !$priv) throw new Exception('RS256 mal configurado (falta JWT_KID o private key)');
            $header['alg'] = 'RS256';
            $header['kid'] = $kid;
            $token = FirebaseJWT::encode($payload, $priv, 'RS256', $kid, $header);
        } else {
            $secret = $this->hsSecret();
            if (!$secret) throw new Exception('HS256 mal configurado (falta JWT_KEYS_CURRENT)');
            $header['alg'] = 'HS256';
            $token = FirebaseJWT::encode($payload, $secret, 'HS256', null, $header);
        }

        // Registrar token (hash) si procede
        $hash = hash('sha256', $token);
        $exp  = isset($payload['exp']) ? date('Y-m-d H:i:s', (int)$payload['exp']) : null;
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO api_tokens (token_hash, expires_at, is_active, created_at) VALUES (?,?,1,NOW())");
        $stmt->execute([$hash, $exp]);

        return $token;
    }

    /** Valida firma, expiración y estado en BD; actualiza last_used_at */
    public function validateToken(string $token): bool
    {
        // Decodificar header para obtener alg/kid si RS256
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true) ?: [];

        if ($this->isRS256()) {
            $kid = $header['kid'] ?? '';
            $pub = $kid ? $this->getPublicKeyByKid($kid) : null;
            if (!$pub) return false;
            FirebaseJWT::decode($token, new Key($pub, 'RS256'));
        } else {
            $secret = $this->hsSecret();
            if (!$secret) return false;
            FirebaseJWT::decode($token, new Key($secret, 'HS256'));
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT is_active, expires_at FROM api_tokens WHERE token_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !(int)$row['is_active']) return false;
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) return false;

        $this->updateTokenLastUsed($hash);
        return true;
    }

    public function getActiveTokens(): array
    {
        $stmt = $this->pdo->query("SELECT token_hash, name, expires_at, last_used_at, is_active, created_at FROM api_tokens WHERE is_active = 1 ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cleanupExpiredTokens(): int
    {
        $stmt = $this->pdo->prepare("UPDATE api_tokens SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function updateTokenLastUsed(string $hash): void
    {
        $stmt = $this->pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?");
        $stmt->execute([$hash]);
    }
}
