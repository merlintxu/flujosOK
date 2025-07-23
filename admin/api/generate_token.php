<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireApiAuth();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');


/* ---------- helpers ---------- */
function generateJWT(array $payload, string $secret, ?int $exp = null): string
{
    $header  = base64_encode(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $claim   = $payload + ['iat'=>time(),'exp'=>$exp ?? time()+3153600000]; // 100 años por defecto
    $body    = base64_encode(json_encode($claim));
    $sig     = base64_encode(hash_hmac('sha256',"$header.$body",$secret,true));
    return str_replace(['+','/','='], ['-','_',''], "$header.$body.$sig");
}

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

$secret = $_ENV['JWT_SECRET'] ?? '';
if ($secret === '') {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'JWT_SECRET not configured']);
    exit;
}
$token  = generateJWT(['name'=>$name,'type'=>'api_access'], $secret, $seconds? time()+$seconds : null);

try {
    /** @var PDO $db */
    $db = $container->resolve(PDO::class);
    $stmt = $db->prepare(
        'INSERT INTO api_tokens (name, token, expires_at, created_at, is_active)
         VALUES (:n,:t,:e,NOW(),1)'
    );
    $stmt->execute([
        ':n'=>$name,
        ':t'=>$token,
        ':e'=>$seconds ? date('Y-m-d H:i:s', time()+$seconds) : null
    ]);

    echo json_encode(['success'=>true,'token'=>$token]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
