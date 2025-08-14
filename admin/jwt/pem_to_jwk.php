<?php
declare(strict_types=1);

/**
 * Uso (CLI o web):
 *   php pem_to_jwk.php /ruta/private.pem [/ruta/public.pem]
 *   # o vía web: /admin/jwt/pem_to_jwk.php?priv=/path/priv.pem&pub=/path/pub.pem
 *
 * Requiere OpenSSL habilitado.
 */
require __DIR__ . '/helpers.php';

// Seguridad mínima (ajusta a tu mecanismo real)
if (php_sapi_name() !== 'cli') {
    // por ejemplo, header('WWW-Authenticate: Basic ...'); die;
}

$privIn = $argv[1] ?? ($_GET['priv'] ?? null);
$pubIn  = $argv[2] ?? ($_GET['pub']  ?? null);

if (!$privIn || !file_exists($privIn)) {
    http_response_code(400);
    exit("Falta private.pem o no existe\n");
}

$priv = openssl_pkey_get_private((string)file_get_contents($privIn));
if ($priv === false) {
    http_response_code(400);
    exit("No se pudo leer la clave privada\n");
}

$details = openssl_pkey_get_details($priv);
if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
    http_response_code(400);
    exit("La clave privada no es RSA o no es válida\n");
}

$pubPem = $pubIn && file_exists($pubIn)
    ? (string)file_get_contents($pubIn)
    : ($details['key'] ?? null);

if (!$pubPem) {
    http_response_code(400);
    exit("No se pudo obtener la clave pública\n");
}

$rsa = $details['rsa'] ?? null;
if (!$rsa) {
    http_response_code(400);
    exit("Detalles RSA no disponibles\n");
}

$n = b64u($rsa['n']);  // modulus
$e = b64u($rsa['e']);  // exponent

$jwkPub = [
    'kty' => 'RSA',
    'n'   => $n,
    'e'   => $e,
    'alg' => 'RS256',
    'use' => 'sig',
];

$kid = kid_from_jwk($jwkPub);
$jwkPub['kid'] = $kid;

// Partes privadas (para JWK privada)
$need = ['d','p','q','dmp1','dmq1','iqmp'];
foreach ($need as $k) {
    if (!isset($rsa[$k])) {
        http_response_code(400);
        exit("La privada no tiene el componente {$k} (¿es realmente privada?)\n");
    }
}
$jwkPriv = $jwkPub + [
    'd'  => b64u($rsa['d']),
    'p'  => b64u($rsa['p']),
    'q'  => b64u($rsa['q']),
    'dp' => b64u($rsa['dmp1']),
    'dq' => b64u($rsa['dmq1']),
    'qi' => b64u($rsa['iqmp']),
];

ensure_dirs();

// Guardar PEM y JWK
$privOut = priv_dir() . "/{$kid}.private.pem";
$pubOut  = pub_dir()  . "/{$kid}.public.pem";

file_put_contents($privOut, (string)file_get_contents($privIn));
file_put_contents($pubOut,  $pubPem);
file_put_contents(priv_dir() . "/{$kid}.private.jwk.json", json_encode($jwkPriv, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

// Actualizar JWKS (público)
$jwks = load_jwks();
$exists = array_filter($jwks['keys'], fn($k) => ($k['kid'] ?? null) === $kid);
if (!$exists) {
    $jwks['keys'][] = $jwkPub;
    save_jwks($jwks);
}

// Marcar como current y sugerir actualización de .env
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
