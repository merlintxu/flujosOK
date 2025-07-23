<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireApiAuth();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;

/** @var RingoverService $ringover */
$ringover = $container->resolve(RingoverService::class);
/** @var CallRepository $repo */
$repo     = $container->resolve('callRepository');

$since    = new DateTimeImmutable('-1 hour');
$download = (bool)($_POST['download'] ?? false);
$inserted = 0;

try {
    foreach ($ringover->getCalls($since) as $call) {
        $repo->insertOrIgnore($call);          // implementa este método si aún no existe
        if ($download && !empty($call['recording_url'])) {
            $ringover->downloadRecording($call['recording_url']);
        }
        $inserted++;
    }
    echo json_encode(['success'=>true,'inserted'=>$inserted]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
