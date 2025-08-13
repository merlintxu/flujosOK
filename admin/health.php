<?php
// Simple health dashboard for API monitoring statistics
// Requires admin login

define('ADMIN_ACCESS', true);
require_once __DIR__ . '/auth.php';
requireLogin();

// Load environment variables (same approach as admin/index.php)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$requiredEnv = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'];
foreach ($requiredEnv as $key) {
    if (empty($_ENV[$key])) {
        http_response_code(500);
        die("Missing environment variable $key");
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_NAME']
);
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$logger = new \FlujosDimension\Core\Logger(dirname(__DIR__) . '/storage/logs');

// Gather metrics for the last hour
$sql = "SELECT service,
               COUNT(*) AS total_requests,
               AVG(response_time) AS avg_latency,
               (SELECT response_time FROM api_monitoring am2 WHERE am2.service = am1.service ORDER BY response_time DESC LIMIT 1 OFFSET FLOOR(COUNT(*) * 0.05)) AS p95_latency,
               SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100 AS error_rate
        FROM api_monitoring am1
        WHERE timestamp >= NOW() - INTERVAL 1 HOUR
        GROUP BY service";
$metrics = $pdo->query($sql)->fetchAll();

$errorThreshold = 5;      // %
$latencyThreshold = 2000; // ms
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Salud</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #f0f0f0; }
        .alert { background: #fdd; }
    </style>
</head>
<body>
<h1>Panel de Salud de APIs</h1>
<table>
    <tr>
        <th>Servicio</th>
        <th>Latencia media (ms)</th>
        <th>P95 (ms)</th>
        <th>Tasa de errores (%)</th>
    </tr>
    <?php foreach ($metrics as $row):
        $alert = ($row['error_rate'] > $errorThreshold) || ($row['p95_latency'] > $latencyThreshold);
        if ($alert) {
            $logger->error('api_health_alert', $row);
        }
    ?>
    <tr class="<?php echo $alert ? 'alert' : ''; ?>">
        <td><?php echo htmlspecialchars($row['service']); ?></td>
        <td><?php echo round((float)$row['avg_latency'], 2); ?></td>
        <td><?php echo round((float)$row['p95_latency'], 2); ?></td>
        <td><?php echo round((float)$row['error_rate'], 2); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
