<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\AnalyticsService;

/** @var AnalyticsService $ai */
$ai = $container->resolve('analyticsService');

$total = 0;
do {
    $ai->processBatch(50);
    $processed = $ai->lastProcessed();   // añade método que devuelva nº filas procesadas en la última tanda
    $total += $processed;
} while ($processed > 0);

echo json_encode(['success'=>true,'processed'=>$total]);
