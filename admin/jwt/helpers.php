<?php
declare(strict_types=1);

function b64u(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function kid_from_jwk(array $jwk): string {
    // RFC 7638 thumbprint: ordenar miembros y seleccionar sólo e,kty,n (para RSA pública)
    $data = [
        'e'   => $jwk['e'],
        'kty' => $jwk['kty'],
        'n'   => $jwk['n'],
    ];
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    return b64u(hash('sha256', $json, true));
}

function base_path(): string {
    // /admin/jwt/helpers.php => 2 niveles arriba
    return dirname(__DIR__, 2);
}

function keys_dir(): string { return base_path() . '/storage/keys'; }
function priv_dir(): string { return keys_dir() . '/private'; }
function pub_dir(): string  { return keys_dir() . '/public'; }
function jwks_path(): string { return keys_dir() . '/jwks.json'; }
function cfg_path(): string  { return base_path() . '/config/jwt.json'; } // estado (current_kid, etc.)

function ensure_dirs(): void {
    foreach ([keys_dir(), priv_dir(), pub_dir()] as $d) {
        if (!is_dir($d)) { mkdir($d, 0755, true); }
    }
}

function load_jwks(): array {
    $p = jwks_path();
    if (!file_exists($p)) return ['keys' => []];
    $j = json_decode((string)file_get_contents($p), true);
    return is_array($j) ? $j : ['keys' => []];
}

function save_jwks(array $jwks): void {
    file_put_contents(jwks_path(), json_encode($jwks, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
}

function set_current_kid(string $kid): void {
    $cfg = ['current_kid' => $kid, 'updated_at' => date(DATE_ATOM)];
    if (!is_dir(dirname(cfg_path()))) mkdir(dirname(cfg_path()), 0755, true);
    file_put_contents(cfg_path(), json_encode($cfg, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
}

function get_current_kid(): ?string {
    if (!file_exists(cfg_path())) return null;
    $cfg = json_decode((string)file_get_contents(cfg_path()), true);
    return $cfg['current_kid'] ?? null;
}

function update_env_kid(string $kid, ?string $privPath = null): bool {
    $env = base_path() . '/.env';
    if (!file_exists($env) || !is_writable($env)) return false;
    $txt = (string)file_get_contents($env);
    $txt = preg_replace('/^JWT_KID=.*$/m', "JWT_KID={$kid}", $txt) ?? $txt;
    $txt = preg_replace('/^JWT_ALG=.*$/m', 'JWT_ALG=RS256', $txt) ?? $txt;
    if ($privPath) {
        $privPath = str_replace('\\', '/', $privPath);
        $txt = preg_replace('/^JWT_PRIVATE_KEY_PATH=.*$/m', "JWT_PRIVATE_KEY_PATH={$privPath}", $txt) ?? $txt;
    }
    file_put_contents($env, $txt);
    return true;
}
