<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');

/* ---------- lógica ---------- */
$name     = $_POST['token_name'] ?? 'Token API';
$duration = $_POST['duration']   ?? 'indefinite';

$seconds  = match($duration){
    '1hour' => 3600,
    '1day'  => 86400,
    '1week' => 604800,
    '1month'=> 2592000,
    '1year' => 31536000,
    default => null               // indefinido
};

try {
    $jwt = new JWT();
    $payload = ['name' => $name, 'type' => 'api_access'];
    if ($seconds) {
        $payload['exp'] = time() + $seconds;
    }
    $token = $jwt->generateToken($payload);

    /** @var PDO $db */
    $db = $container->resolve(PDO::class);
    $stmt = $db->prepare('UPDATE api_tokens SET name = :n WHERE token_hash = :h');
    $stmt->execute([':n' => $name, ':h' => hash('sha256', $token)]);

    echo json_encode(['success'=>true,'token'=>['token'=>$token]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
