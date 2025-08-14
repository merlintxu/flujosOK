<?php
declare(strict_types=1);
namespace FlujosDimension\Services;

use Exception;

class JwtKeyService
{
    public function __construct(private string $basePath) {
        $this->ensureDirs();
    }

    private function keysDir(): string { return $this->basePath.'/storage/keys'; }
    private function privDir(): string { return $this->keysDir().'/private'; }
    private function pubDir(): string  { return $this->keysDir().'/public'; }
    private function jwksPath(): string { return $this->keysDir().'/jwks.json'; }
    private function cfgPath(): string  { return $this->basePath.'/config/jwt.json'; }

    private static function b64u(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
    private static function kid(array $jwk): string {
        $payload = json_encode(['e'=>$jwk['e'],'kty'=>$jwk['kty'],'n'=>$jwk['n']], JSON_UNESCAPED_SLASHES);
        return self::b64u(hash('sha256', $payload, true));
    }

    private function ensureDirs(): void {
        foreach ([$this->keysDir(), $this->privDir(), $this->pubDir(), dirname($this->cfgPath())] as $d) {
            if (!is_dir($d)) mkdir($d, 0755, true);
        }
        if (!file_exists($this->jwksPath())) {
            file_put_contents($this->jwksPath(), json_encode(['keys'=>[]], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
        }
    }

    private function loadJwks(): array {
        return json_decode((string)file_get_contents($this->jwksPath()), true) ?: ['keys'=>[]];
    }
    private function saveJwks(array $jwks): void {
        file_put_contents($this->jwksPath(), json_encode($jwks, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }

    private function setCurrentKid(string $kid): void {
        file_put_contents($this->cfgPath(), json_encode(['current_kid'=>$kid,'updated_at'=>date(DATE_ATOM)], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }

    private function updateEnv(string $kid, ?string $privPath=null): void {
        $env = $this->basePath.'/.env';
        if (!file_exists($env) || !is_writable($env)) return;
        $txt = (string)file_get_contents($env);
        $set = function(string $k,string $v) use (&$txt){
            $p='/^'.preg_quote($k,'/').'=.*$/m';
            $txt = preg_match($p,$txt) ? preg_replace($p,"$k=$v",$txt) : ($txt.PHP_EOL."$k=$v");
        };
        $set('JWT_ALG','RS256');
        $set('JWT_KID',$kid);
        if ($privPath) $set('JWT_PRIVATE_KEY_PATH', str_replace('\\','/',$privPath));
        $set('JWT_JWKS_PATH', str_replace('\\','/',$this->jwksPath()));
        file_put_contents($env, $txt);
    }

    public function generate(): array
    {
        $res = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA, 'private_key_bits'=>4096]);
        if (!$res) throw new Exception('No se pudo generar la clave');
        openssl_pkey_export($res, $privPem);
        $det = openssl_pkey_get_details($res);
        $pubPem = $det['key']; $rsa=$det['rsa'];
        $jwkPub = ['kty'=>'RSA','n'=>self::b64u($rsa['n']),'e'=>self::b64u($rsa['e']),'alg'=>'RS256','use'=>'sig'];
        $kid = self::kid($jwkPub); $jwkPub['kid']=$kid;
        $jwkPriv = $jwkPub + ['d'=>self::b64u($rsa['d']),'p'=>self::b64u($rsa['p']),'q'=>self::b64u($rsa['q']),'dp'=>self::b64u($rsa['dmp1']),'dq'=>self::b64u($rsa['dmq1']),'qi'=>self::b64u($rsa['iqmp'])];

        $privOut=$this->privDir()."/{$kid}.private.pem"; $pubOut=$this->pubDir()."/{$kid}.public.pem";
        file_put_contents($privOut,$privPem); file_put_contents($pubOut,$pubPem);
        file_put_contents($this->privDir()."/{$kid}.private.jwk.json", json_encode($jwkPriv, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

        $jwks=$this->loadJwks(); $jwks['keys'][]=$jwkPub; $this->saveJwks($jwks);
        $this->setCurrentKid($kid); $this->updateEnv($kid, $privOut);

        return ['kid'=>$kid,'privatePem'=>$privOut,'publicPem'=>$pubOut,'jwks'=>$this->jwksPath()];
    }

    public function import(string $privPemPath, ?string $pubPemPath=null): array
    {
        if (!file_exists($privPemPath)) throw new Exception('private.pem no existe');
        $priv = openssl_pkey_get_private((string)file_get_contents($privPemPath));
        if (!$priv) throw new Exception('No se pudo cargar la privada');
        $det = openssl_pkey_get_details($priv);
        if (($det['type']??null)!==OPENSSL_KEYTYPE_RSA) throw new Exception('Clave no RSA');

        $pubPem = ($pubPemPath && file_exists($pubPemPath))
            ? (string)file_get_contents($pubPemPath)
            : ($det['key'] ?? null);
        if (!$pubPem) throw new Exception('No se pudo obtener la pública');

        $rsa=$det['rsa'];
        foreach (['d','p','q','dmp1','dmq1','iqmp'] as $k) {
            if (!isset($rsa[$k])) throw new Exception("Privada incompleta: falta $k");
        }

        $jwkPub = ['kty'=>'RSA','n'=>self::b64u($rsa['n']),'e'=>self::b64u($rsa['e']),'alg'=>'RS256','use'=>'sig'];
        $kid = self::kid($jwkPub); $jwkPub['kid']=$kid;
        $jwkPriv = $jwkPub + ['d'=>self::b64u($rsa['d']),'p'=>self::b64u($rsa['p']),'q'=>self::b64u($rsa['q']),'dp'=>self::b64u($rsa['dmp1']),'dq'=>self::b64u($rsa['dmq1']),'qi'=>self::b64u($rsa['iqmp'])];

        $privOut=$this->privDir()."/{$kid}.private.pem"; $pubOut=$this->pubDir()."/{$kid}.public.pem";
        copy($privPemPath,$privOut); file_put_contents($pubOut,$pubPem);
        file_put_contents($this->privDir()."/{$kid}.private.jwk.json", json_encode($jwkPriv, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

        $jwks=$this->loadJwks();
        $exists = array_filter($jwks['keys'], fn($k)=>($k['kid']??'')===$kid);
        if (!$exists) { $jwks['keys'][]=$jwkPub; $this->saveJwks($jwks); }

        $this->setCurrentKid($kid); $this->updateEnv($kid, $privOut);
        return ['kid'=>$kid,'privatePem'=>$privOut,'publicPem'=>$pubOut,'jwks'=>$this->jwksPath()];
    }

    public function rotate(string $kid): void
    {
        $jwks=$this->loadJwks();
        $exists = array_filter($jwks['keys'], fn($k)=>($k['kid']??'')===$kid);
        if (!$exists) throw new Exception('KID no existe en JWKS');
        $this->setCurrentKid($kid);
        $priv = $this->privDir()."/{$kid}.private.pem";
        $this->updateEnv($kid, file_exists($priv)?$priv:null);
    }

    public function jwks(): array { return $this->loadJwks(); }
}
