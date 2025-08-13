<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\CallService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Core\Request;

// Configuración de niveles de log
if (!defined('LOG_LEVEL_DEBUG')) {
    define('LOG_LEVEL_DEBUG', 0);
}
if (!defined('LOG_LEVEL_INFO')) {
    define('LOG_LEVEL_INFO', 1);
}
if (!defined('LOG_LEVEL_ERROR')) {
    define('LOG_LEVEL_ERROR', 2);
}

// Inicializa el log
$logDir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$GLOBALS['logFile'] = $logDir . '/sync_ringover.log';

// Base storage directories
$baseStorage   = dirname(__DIR__, 2) . '/storage';
$recordingsDir = $baseStorage . '/recordings';
$voicemailsDir = $baseStorage . '/voicemails';
foreach ([$recordingsDir, $voicemailsDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

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
        // Allow HTML datetime-local ("Y-m-dTH:i") or "Y-m-d H:i"
        $sinceStr = str_replace('T', ' ', $sinceStr);
        $tz = new \DateTimeZone('Europe/Madrid');
        try {
            return new \DateTimeImmutable($sinceStr, $tz);
        } catch (\Exception $e) {
            writeLog(LOG_LEVEL_ERROR, 'Invalid since parameter', [
                'since' => $sinceStr,
                'error' => $e->getMessage(),
            ]);
            throw new \InvalidArgumentException('Invalid since parameter');
        }
    }
}

if (!function_exists('collect_params')) {
    /**
     * Validate input parameters using filter_var rules without exiting early.
     *
     * @param array<string, array{filter:int, required?:bool}> $rules
     * @return array<string, mixed>
     */
    function collect_params(Request $request, array $rules): array {
        $data = [];
        foreach ($rules as $name => $opts) {
            $required = $opts['required'] ?? false;
            $filter   = $opts['filter']   ?? FILTER_DEFAULT;
            $value    = $request->post($name);
            if ($value === null) {
                $value = $request->get($name);
            }
            if ($value === null) {
                if ($required) {
                    throw new \InvalidArgumentException("Missing field: $name");
                }
                continue;
            }
            $filtered = filter_var($value, $filter, ['flags' => FILTER_NULL_ON_FAILURE]);
            if ($filtered === null && $filter !== FILTER_DEFAULT) {
                throw new \InvalidArgumentException("Invalid value for $name");
            }
            $data[$name] = $filtered ?? $value;
        }
        return $data;
    }
}

writeLog(LOG_LEVEL_INFO, 'Starting Ringover sync process', [
    'log_level' => ['DEBUG','INFO','ERROR'][$GLOBALS['CURRENT_LOG_LEVEL']],
]);

// Inicializa servicios y registra en el log
/** @var CallService $ringoverService */
$ringoverService = $container->resolve(CallService::class);
/** @var CallRepository $repo */
$repo = $container->resolve('callRepository');
writeLog(LOG_LEVEL_DEBUG, 'RingoverService and CallRepository initialized');


// Initialize defaults
$inserted = 0;
$downloads = 0;
$errors = [];
$code = 200;
$response = [];

try {
    // Parameters may be provided via POST body or GET query string
    $params = collect_params($request, [
        'download' => ['filter' => FILTER_VALIDATE_BOOLEAN],
        'full'     => ['filter' => FILTER_VALIDATE_BOOLEAN],
        'fields'   => ['filter' => FILTER_UNSAFE_RAW],
        'since'    => ['filter' => FILTER_UNSAFE_RAW]
    ]);

    writeLog(LOG_LEVEL_DEBUG, 'Input parameters received', $params);

    $download = $params['download'] ?? false;
    $full     = $params['full'] ?? $download;
    $fields   = isset($params['fields']) ? sanitize_string((string)$params['fields']) : null;
    $sinceStr = sanitize_string((string)($params['since'] ?? '-1 hour'));

    $since = parseSince($sinceStr);
    if ($full || $fields !== null) {
        $calls = $ringoverService->getCalls($since, $full, $fields);
    } else {
        $calls = $ringoverService->getCalls($since);
    }
    $retrieved = 0;
    $loggedApiCall = false;

    $currentPage = 0;
    foreach ($calls as $call) {
        if (!$loggedApiCall) {
            writeLog(LOG_LEVEL_INFO, 'Calling Ringover API', ['since' => $since->format(\DateTimeInterface::ATOM)]);
            $loggedApiCall = true;
        }

        $page = $call['_page'] ?? 0;
        unset($call['_page']);
        if ($page !== $currentPage) {
            $currentPage = $page;
            writeLog(LOG_LEVEL_INFO, 'Processing page', ['page' => $currentPage]);
        }

        $retrieved++;
        writeLog(LOG_LEVEL_DEBUG, 'Processing call', $call);
        $mapped = $ringoverService->mapCallFields($call);

        if (empty($mapped['ringover_id'])) {
            writeLog(LOG_LEVEL_ERROR, 'Skipping call without ringover_id', $call);
            continue;
        }

        $result = $repo->insertOrIgnore($mapped);

        $ringId = (string)($mapped['ringover_id'] ?? '');
        $callId = null;
        if (method_exists($repo, 'findIdByRingoverId') && $ringId !== '') {
            $callId = $repo->findIdByRingoverId($ringId);
        }

        $hasMedia = !empty($mapped['recording_url']) || !empty($mapped['voicemail_url']);

        if ($hasMedia && $callId !== null && method_exists($repo, 'setPendingAnalysis')) {
            $repo->setPendingAnalysis($callId, true);
        }

        if ($full && $hasMedia && $callId !== null) {
            try {
                if (!empty($mapped['recording_url']) && method_exists($ringoverService, 'downloadRecording')) {
                    writeLog(LOG_LEVEL_INFO, 'Downloading recording', ['url' => $mapped['recording_url']]);
                    $info = $ringoverService->downloadRecording($mapped['recording_url'], $recordingsDir);
                } elseif (!empty($mapped['voicemail_url']) && method_exists($ringoverService, 'downloadRecording')) {
                    writeLog(LOG_LEVEL_INFO, 'Downloading voicemail', ['url' => $mapped['voicemail_url']]);
                    $info = $ringoverService->downloadRecording($mapped['voicemail_url'], $voicemailsDir);
                } else {
                    $info = null;
                }

                if ($info !== null && method_exists($repo, 'addRecording')) {
                    $info['url'] = $info['url'] ?? ($mapped['recording_url'] ?? $mapped['voicemail_url']);
                    $repo->addRecording($callId, $info);
                }

                if ($callId !== null && method_exists($repo, 'setPendingRecordings')) {
                    $repo->setPendingRecordings($callId, false);
                }
                $downloads++;
            } catch (\Throwable $e) {
                writeLog(LOG_LEVEL_ERROR, 'Recording download failed', ['error' => $e->getMessage()]);
                $errors[] = [
                    'type'    => 'download',
                    'call_id' => $ringId,
                    'message' => $e->getMessage(),
                ];
                if ($callId !== null && method_exists($repo, 'setPendingRecordings')) {
                    $repo->setPendingRecordings($callId, true);
                }
            }
        } elseif ($hasMedia && $callId !== null && method_exists($repo, 'setPendingRecordings')) {
            // Media exists but not downloaded
            $repo->setPendingRecordings($callId, true);
        }

        if ($result > 0) {
            $inserted++;
        }
    }

    writeLog(LOG_LEVEL_DEBUG, 'Total calls retrieved from Ringover API', ['count' => $retrieved]);
    writeLog(LOG_LEVEL_INFO, 'Sync completed', ['retrieved' => $retrieved, 'inserted' => $inserted, 'downloads' => $downloads, 'errors' => count($errors)]);
    $response = [
        'success' => empty($errors),
        'data' => [
            'retrieved' => $retrieved,
            'inserted'  => $inserted,
            'downloads' => $downloads,
            'errors'    => $errors,
        ],
    ];
} catch (Throwable $e) {
    $code = $e instanceof \InvalidArgumentException ? 400 : 500;
    writeLog(LOG_LEVEL_ERROR, 'Exception occurred', ['error' => $e->getMessage()]);
    $response = [
        'success' => false,
        'error'   => $e->getMessage(),
    ];
} finally {
    http_response_code($code);
    echo json_encode($response);
    return;
}
