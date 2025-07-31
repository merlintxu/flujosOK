<?php
declare(strict_types=1);

require __DIR__ . '/init.php';


validate_fields($request, ['token_name']);

/* ---------- lógica ---------- */
$name     = sanitize_string((string)$request->post('token_name', 'Token API'));
$duration = $request->post('duration', 'indefinite');

$allowedDurations = ['1hour','1day','1week','1month','1year','indefinite'];
if (!in_array($duration, $allowedDurations, true)) {
    respond_error('Invalid duration');
}

$seconds  = match($duration){
    '1hour' => 3600,
    '1day'  => 86400,
    '1week' => 604800,
    '1month'=> 2592000,
    '1year' => 31536000,
    default => null               // indefinido
};

try {
    /** @var FlujosDimension\Core\JWT $jwt */
    $jwt = $container->resolve('jwtService');
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
