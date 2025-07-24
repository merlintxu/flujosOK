<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth.php';
requireApiAuth();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;


$ringover = $container->resolve(RingoverService::class);
$repo     = $container->resolve('callRepository');

$since    = new DateTimeImmutable('-1 hour');
$download = (bool)($_POST['download'] ?? false);
$inserted = 0;

try {
    $jsonDump = [];
    foreach ($ringover->getCalls($since) as $call) {
        $jsonDump[] = $call;
        $repo->insertOrIgnore($call);          
        if ($download && !empty($call['recording_url'])) {
            $ringover->downloadRecording($call['recording_url']);
        }
        $inserted++;
    }
   
    echo json_encode(['success'=>true,'inserted'=>$inserted]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
