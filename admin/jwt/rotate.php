<?php
declare(strict_types=1);

/**
 * Uso:
 *   php rotate.php <kid>
 *   # o web: /admin/jwt/rotate.php?kid=xxxxx
 */
require __DIR__ . '/helpers.php';

// Seguridad mínima (ajusta a tu mecanismo real)
if (php_sapi_name() !== 'cli') {
    // p.ej. Basic Auth...
}

$kid = $argv[1] ?? ($_GET['kid'] ?? null);
if (!$kid) { http_response_code(400); exit("Falta kid\n"); }

$jwks = load_jwks();
$exists = array_filter($jwks['keys'], fn($k) => ($k['kid'] ?? '') === $kid);
if (!$exists) { http_response_code(404); exit("KID no encontrado en JWKS\n"); }

set_current_kid($kid);

// Si quieres, intenta localizar la privada asociada y actualizar .env
$privPath = priv_dir() . "/{$kid}.private.pem";
update_env_kid($kid, file_exists($privPath) ? $privPath : null);

header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'current_kid' => $kid], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
