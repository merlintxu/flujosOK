<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
$jwks = load_jwks();

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // 5 min
header('Access-Control-Allow-Origin: *');
echo json_encode($jwks, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
