<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');

use App\Services\AnalyticsService;

/** @var AnalyticsService $ai */
$ai = $container->resolve('analyticsService');

$total = 0;
do {
    $ai->processBatch(50);
    $processed = $ai->lastProcessed();   // añade método que devuelva nº filas procesadas en la última tanda
    $total += $processed;
} while ($processed > 0);

echo json_encode(['success'=>true,'processed'=>$total]);
