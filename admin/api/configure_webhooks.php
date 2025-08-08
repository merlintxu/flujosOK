<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;

/** @var Config $config */
$config = $container->resolve(Config::class);
/** @var HttpClient $http */
$http   = $container->resolve(HttpClient::class);

$logDir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/webhook_config.log';

function log_line(string $message): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$message}\n", FILE_APPEND);
}

$baseUrl   = rtrim((string)$config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2'), '/');
$apiKey    = (string)$config->get('RINGOVER_API_KEY', '');
$appUrl    = rtrim((string)$config->get('APP_URL', 'https://example.com'), '/');
$webhookBase = $appUrl . '/api/v3/webhooks/ringover';

$events = [
    ['event' => 'recording.available', 'path' => '/record-available'],
    ['event' => 'voicemail.available', 'path' => '/voicemail-available'],
];

log_line('Starting webhook configuration');

try {
    foreach ($events as $e) {
        log_line('Configuring event: ' . $e['event']);
        $resp = $http->request('POST', "$baseUrl/webhooks", [
            'headers' => ['Authorization' => $apiKey],
            'json' => [
                'event' => $e['event'],
                'url'   => $webhookBase . $e['path'],
            ],
        ]);

        $status = $resp->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Unexpected status ' . $status);
        }
    }

    log_line('Webhooks configured successfully');
    echo json_encode(['success' => true, 'message' => 'Webhooks configured']);
} catch (Throwable $e) {
    log_line('Webhook configuration failed: ' . $e->getMessage());
    $steps = [
        '1. Sign in to Ringover dashboard.',
        "2. Create webhook for \"recording.available\" pointing to {$webhookBase}/record-available.",
        "3. Create webhook for \"voicemail.available\" pointing to {$webhookBase}/voicemail-available.",
        '4. Ensure the signing secret matches RINGOVER_WEBHOOK_SECRET in your environment.',
    ];
    echo json_encode([
        'success' => false,
        'message' => 'Automatic webhook setup failed or not supported. Configure manually.',
        'manual_steps' => $steps,
    ], JSON_PRETTY_PRINT);
}

exit(0);
