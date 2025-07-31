<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\AnalyticsService;

/** @var AnalyticsService $ai */
$ai = $container->resolve('analyticsService');

$params = validate_input($request, [
    'max' => ['filter' => FILTER_VALIDATE_INT]
]);
$max = $params['max'] ?? 50;
if ($max <= 0) {
    respond_error('Invalid max parameter');
}

$total = 0;
do {
    $ai->processBatch($max);
    $processed = $ai->lastProcessed();   // añade método que devuelva nº filas procesadas en la última tanda
    $total += $processed;
} while ($processed > 0);

echo json_encode(['success'=>true,'processed'=>$total]);
