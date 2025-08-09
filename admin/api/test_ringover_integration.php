<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;

$logDir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/integration_test.log';

function log_line(string $message): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$message}\n", FILE_APPEND);
}

try {
    /** @var RingoverService $ringover */
    $ringover = $container->resolve(RingoverService::class);
    /** @var CallRepository $repo */
    $repo     = $container->resolve('callRepository');

    $params = validate_input($request, [
        'start_date' => ['filter' => FILTER_UNSAFE_RAW, 'required' => true],
    ]);

    try {
        $start = new DateTimeImmutable((string)$params['start_date']);
    } catch (Throwable $e) {
        log_line('Invalid start_date: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Invalid start_date']);
        exit(1);
    }

    log_line('Starting integration test at ' . $start->format(DATE_ATOM));

    $auth = $ringover->testConnection();
    if (!($auth['success'] ?? false)) {
        $msg = $auth['message'] ?? 'Ringover authentication failed';
        log_line('Ringover authentication failed: ' . $msg);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit(1);
    }
    log_line('Authentication successful');

    $calls = iterator_to_array($ringover->getCalls($start));
    $count = count($calls);
    log_line('Calls retrieved: ' . $count);
    if ($count === 0) {
        echo json_encode(['success' => false, 'message' => 'No calls returned']);
        exit(1);
    }

    $sample = $calls[0];
    $mapped = $ringover->mapCallFields($sample);
    $inserted = $repo->insertOrIgnore($mapped);
    if ($inserted === 0) {
        log_line('Database insert failed');
        echo json_encode(['success' => false, 'message' => 'Database insert failed']);
        exit(1);
    }
    log_line('Database write ok');

    if (empty($mapped['recording_url'])) {
        log_line('No recording URL available');
        echo json_encode(['success' => false, 'message' => 'No recording URL available']);
        exit(1);
    }

    try {
        $info = $ringover->downloadRecording($mapped['recording_url'], 'recordings');
        log_line('Recording downloaded to ' . $info['path']);
    } catch (Throwable $e) {
        log_line('Recording download failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Recording download failed']);
        exit(1);
    }

    echo json_encode(['success' => true, 'calls' => $count]);
    exit(0);
} catch (Throwable $e) {
    log_line('Unexpected error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit(1);
}
