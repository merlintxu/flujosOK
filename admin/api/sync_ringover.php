<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Core\Request;

// Configuración de niveles de log
define('LOG_LEVEL_DEBUG', 0);
define('LOG_LEVEL_INFO', 1);
define('LOG_LEVEL_ERROR', 2);

// Inicializa el log
$logDir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$GLOBALS['logFile'] = $logDir . '/sync_ringover.log';

if (!function_exists('determine_log_level')) {
    function determine_log_level(Request $request): int {
        $value = $request->get('log_level') ?? $request->post('log_level') ?? getenv('RINGOVER_LOG_LEVEL');
        $value = $value ? strtoupper((string) $value) : '';
        $map = [
            'DEBUG' => LOG_LEVEL_DEBUG,
            'INFO'  => LOG_LEVEL_INFO,
            'ERROR' => LOG_LEVEL_ERROR,
        ];
        return $map[$value] ?? LOG_LEVEL_DEBUG;
    }
}

$GLOBALS['CURRENT_LOG_LEVEL'] = determine_log_level($request);

if (!function_exists('writeLog')) {
    function writeLog($level, $message, $data = null) {
        if ($level < $GLOBALS['CURRENT_LOG_LEVEL']) return;
        $levels = ['DEBUG', 'INFO', 'ERROR'];
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$levels[$level]}] {$message}";
        if ($data !== null) {
            $logMessage .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
        }
        $logMessage .= "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($GLOBALS['logFile'], $logMessage, FILE_APPEND);
    }
}

if (!function_exists('parseSince')) {
    function parseSince(string $sinceStr): \DateTimeImmutable {
        try {
            return new \DateTimeImmutable($sinceStr);
        } catch (\Exception $e) {
            writeLog(LOG_LEVEL_ERROR, 'Invalid since parameter', [
                'since' => $sinceStr,
                'error' => $e->getMessage(),
            ]);
            respond_error('Invalid since parameter');
        }
    }
}

writeLog(LOG_LEVEL_INFO, 'Starting Ringover sync process', [
    'log_level' => ['DEBUG','INFO','ERROR'][$GLOBALS['CURRENT_LOG_LEVEL']],
]);

// Inicializa servicios y registra en el log
/** @var RingoverService $ringoverService */
$ringoverService = $container->resolve(RingoverService::class);
/** @var CallRepository $repo */
$repo = $container->resolve('callRepository');
writeLog(LOG_LEVEL_DEBUG, 'RingoverService and CallRepository initialized');


$params = validate_input($request, [
    'download' => ['filter' => FILTER_VALIDATE_BOOLEAN],
    'since'    => ['filter' => FILTER_UNSAFE_RAW]
]);

writeLog(LOG_LEVEL_DEBUG, 'Input parameters received', $params);

$download = $params['download'] ?? false;
$sinceStr = sanitize_string((string)($params['since'] ?? '-1 hour'));
$since = parseSince($sinceStr);
$inserted = 0;

try {
    $calls = $ringoverService->getCalls($since);
    $retrieved = 0;
    $loggedApiCall = false;

    foreach ($calls as $call) {
        if (!$loggedApiCall) {
            writeLog(LOG_LEVEL_INFO, 'Calling Ringover API', ['since' => $since->format(\DateTimeInterface::ATOM)]);
            $loggedApiCall = true;
        }

        $retrieved++;
        writeLog(LOG_LEVEL_DEBUG, 'Processing call', $call);
        $affected = $repo->insertOrIgnore($call);
        if ($download && !empty($call['recording_url'])) {
            writeLog(LOG_LEVEL_INFO, 'Downloading recording', ['url' => $call['recording_url']]);
            $ringoverService->downloadRecording($call['recording_url']);
        }
        if ($affected > 0) {
            $inserted++;
        }
    }

    writeLog(LOG_LEVEL_DEBUG, 'Total calls retrieved from Ringover API', ['count' => $retrieved]);
    writeLog(LOG_LEVEL_INFO, 'Sync completed', ['inserted' => $inserted]);
    echo json_encode(['success'=>true,'inserted'=>$inserted]);
} catch (Throwable $e) {
    writeLog(LOG_LEVEL_ERROR, 'Exception occurred', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
