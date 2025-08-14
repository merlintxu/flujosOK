<?php
declare(strict_types=1);

/**
 * Uso:
 *   php generate_rsa.php
 *   # o vía web: /admin/jwt/generate_rsa.php
 */
require __DIR__ . '/helpers.php';

$config = [
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 4096,
];

$privRes = openssl_pkey_new($config);
if (!$privRes) { http_response_code(500); exit("No se pudo generar la clave\n"); }

if (!openssl_pkey_export($privRes, $privPem)) {
    http_response_code(500); exit("No se pudo exportar privada\n");
}

$details = openssl_pkey_get_details($privRes);
if (!$details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
    http_response_code(500); exit("Detalles RSA inválidos\n");
}
$pubPem = $details['key'] ?? null;
$rsa    = $details['rsa'] ?? null;

$n = b64u($rsa['n']);
$e = b64u($rsa['e']);

$jwkPub = [
    'kty' => 'RSA',
    'n'   => $n,
    'e'   => $e,
    'alg' => 'RS256',
    'use' => 'sig',
];

$kid = kid_from_jwk($jwkPub);
$jwkPub['kid'] = $kid;

$jwkPriv = $jwkPub + [
    'd'  => b64u($rsa['d']),
    'p'  => b64u($rsa['p']),
    'q'  => b64u($rsa['q']),
    'dp' => b64u($rsa['dmp1']),
    'dq' => b64u($rsa['dmq1']),
    'qi' => b64u($rsa['iqmp']),
];

ensure_dirs();

// Persistir
$privOut = priv_dir() . "/{$kid}.private.pem";
$pubOut  = pub_dir()  . "/{$kid}.public.pem";

file_put_contents($privOut, $privPem);
file_put_contents($pubOut,  $pubPem);
file_put_contents(priv_dir() . "/{$kid}.private.jwk.json", json_encode($jwkPriv, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

$jwks = load_jwks();
$jwks['keys'][] = $jwkPub;
save_jwks($jwks);

// Marcar current y actualizar .env
set_current_kid($kid);
update_env_kid($kid, $privOut);

header('Content-Type: application/json');
echo json_encode([
    'status'     => 'ok',
    'kid'        => $kid,
    'privatePem' => $privOut,
    'publicPem'  => $pubOut,
    'jwks'       => jwks_path(),
    'envHints'   => [
        'JWT_ALG=RS256',
        "JWT_KID={$kid}",
        "JWT_PRIVATE_KEY_PATH={$privOut}",
        "JWT_JWKS_PATH=" . jwks_path(),
    ],
], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
