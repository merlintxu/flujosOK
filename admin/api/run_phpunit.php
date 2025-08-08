<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireApiAuth();

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__, 2);
$phpunit = $root . '/vendor/bin/phpunit';
$logDir = $root . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/integration_test.log';

$logHandle = fopen($logFile, 'a');

function stripAnsi(string $text): string {
    return preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text);
}

$proc = popen(escapeshellcmd($phpunit) . ' 2>&1', 'r');
if (!is_resource($proc)) {
    http_response_code(500);
    echo "Unable to run tests\n";
    if ($logHandle) {
        fwrite($logHandle, "[" . date('Y-m-d H:i:s') . "] Unable to run tests\n");
        fclose($logHandle);
    }
    exit;
}

while (!feof($proc)) {
    $line = fgets($proc);
    if ($line === false) {
        break;
    }
    $clean = stripAnsi($line);
    $sanitized = htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
    echo $sanitized;
    if ($logHandle) {
        fwrite($logHandle, $clean);
    }
    flush();
}

pclose($proc);
if ($logHandle) {
    fclose($logHandle);
}
