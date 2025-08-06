<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;

/** @var RingoverService $ringover */
$ringover = $container->resolve(RingoverService::class);
/** @var CallRepository $repo */
$repo     = $container->resolve('callRepository');

$params   = validate_input($request, [
    'download' => ['filter' => FILTER_VALIDATE_BOOLEAN],
    // FILTER_SANITIZE_STRING is deprecated; use a permitted filter and sanitize manually
    'since'    => ['filter' => FILTER_UNSAFE_RAW]
]);

$download = $params['download'] ?? false;

$sinceStr = sanitize_string((string)($params['since'] ?? '-1 hour'));
$since    = @\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $sinceStr) ?: new \DateTimeImmutable($sinceStr);
if (!$since) {
    respond_error('Invalid since parameter');
}
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
