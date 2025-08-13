<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Controllers\RingoverWebhookController;
use PDO;

class RingoverWebhookControllerTest extends TestCase
{
    private function container(PDO $pdo, string $secret): Container
    {
        $c = new Container();
        $c->instance('logger', new \Psr\Log\NullLogger());
        $c->instance('config', ['RINGOVER_WEBHOOK_SECRET' => $secret]);
        $c->instance('database', $pdo);
        $c->instance(\FlujosDimension\Repositories\AsyncTaskRepository::class, new \FlujosDimension\Repositories\AsyncTaskRepository($pdo));
        return $c;
    }

    private function request(string $method, string $uri, string $body, string $signature): Request
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_X_RINGOVER_SIGNATURE' => $signature,
        ];
        return new Request($body);
    }

    public function testRecordAvailableQueuesJob(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE async_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id TEXT, task_type TEXT, task_data TEXT, priority INTEGER DEFAULT 5, status TEXT DEFAULT 'pending', attempts INTEGER DEFAULT 0, visible_at TEXT, reserved_at TEXT, error_reason TEXT, dlq INTEGER DEFAULT 0, max_attempts INTEGER DEFAULT 3, retry_backoff_sec INTEGER DEFAULT 60, created_at TEXT);");
        $pdo->exec("CREATE TABLE webhook_deduplication (id INTEGER PRIMARY KEY AUTOINCREMENT, deduplication_key TEXT, webhook_type TEXT, payload_hash TEXT, correlation_id TEXT, processed_at TEXT, expires_at TEXT);");
        $pdo->exec("CREATE TABLE webhook_processing_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, webhook_type TEXT, deduplication_key TEXT, correlation_id TEXT, status TEXT, payload_size INTEGER, processing_time_ms INTEGER, error_message TEXT);");

        $secret = 'topsecret';
        $body = json_encode(['call_id' => 'r1', 'recording_url' => 'http://example.com/a.mp3', 'duration' => 5]);
        $sig = hash_hmac('sha256', $body, $secret);

        $c = $this->container($pdo, $secret);

        $controller = new RingoverWebhookController($c, $this->request('POST', '/api/v3/webhooks/ringover/record-available', $body, $sig));
        $resp = $controller->recordAvailable();
        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['queued']);

        $row = $pdo->query('SELECT task_type, task_data FROM async_tasks')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(\FlujosDimension\Jobs\DownloadRecordingJob::class, $row['task_type']);
        $payload = json_decode($row['task_data'], true);
        $this->assertSame('r1', $payload['call_id']);
    }

    public function testInvalidSignatureRejected(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE async_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id TEXT, task_type TEXT, task_data TEXT, priority INTEGER DEFAULT 5, status TEXT DEFAULT 'pending', attempts INTEGER DEFAULT 0, visible_at TEXT, reserved_at TEXT, error_reason TEXT, dlq INTEGER DEFAULT 0, max_attempts INTEGER DEFAULT 3, retry_backoff_sec INTEGER DEFAULT 60, created_at TEXT);");
        $pdo->exec("CREATE TABLE webhook_deduplication (id INTEGER PRIMARY KEY AUTOINCREMENT, deduplication_key TEXT, webhook_type TEXT, payload_hash TEXT, correlation_id TEXT, processed_at TEXT, expires_at TEXT);");
        $pdo->exec("CREATE TABLE webhook_processing_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, webhook_type TEXT, deduplication_key TEXT, correlation_id TEXT, status TEXT, payload_size INTEGER, processing_time_ms INTEGER, error_message TEXT);");

        $secret = 'abc';
        $body = json_encode(['call_id' => 'r1', 'recording_url' => 'http://e/a.mp3']);
        $sig = 'bad';

        $c = $this->container($pdo, $secret);
        $controller = new RingoverWebhookController($c, $this->request('POST', '/api/v3/webhooks/ringover/record-available', $body, $sig));
        $resp = $controller->recordAvailable();
        $this->assertSame(401, $resp->getStatusCode());
    }
}
