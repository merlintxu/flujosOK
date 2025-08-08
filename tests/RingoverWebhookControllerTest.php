<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Controllers\RingoverWebhookController;
use FlujosDimension\Services\RingoverService;
use PDO;

class RingoverWebhookControllerTest extends TestCase
{
    private function container(PDO $pdo, string $secret): Container
    {
        $c = new Container();
        $c->instance('logger', new class { public function error(...$a){} public function info(...$a){} });
        $c->instance('config', ['RINGOVER_WEBHOOK_SECRET' => $secret]);
        $c->instance('database', $pdo);
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

    public function testRecordAvailableStoresMetadata(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, ringover_id TEXT, recording_url TEXT, recording_path TEXT, has_recording INTEGER DEFAULT 0);");
        $pdo->exec("CREATE TABLE call_recordings (id INTEGER PRIMARY KEY AUTOINCREMENT, call_id INTEGER, file_path TEXT, file_size INTEGER, duration INTEGER, format TEXT);");
        $pdo->exec("INSERT INTO calls (ringover_id) VALUES ('r1')");

        $secret = 'topsecret';
        $body = json_encode(['call_id' => 'r1', 'recording_url' => 'http://example.com/a.mp3', 'duration' => 5]);
        $sig = hash_hmac('sha256', $body, $secret);

        $c = $this->container($pdo, $secret);
        $c->instance(RingoverService::class, new class extends RingoverService {
            public function __construct(){}
            public function downloadRecording(string $url, string $dir = 'storage/recordings'): string
            {
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                $path = $dir . '/test.mp3';
                file_put_contents($path, 'data');
                return $path;
            }
        });

        $controller = new RingoverWebhookController($c, $this->request('POST', '/api/v3/webhooks/ringover/record-available', $body, $sig));
        $resp = $controller->recordAvailable();
        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success']);

        $call = $pdo->query("SELECT recording_url, recording_path, has_recording FROM calls WHERE ringover_id='r1'")->fetch();
        $this->assertSame('http://example.com/a.mp3', $call['recording_url']);
        $this->assertNotEmpty($call['recording_path']);
        $this->assertSame(1, (int)$call['has_recording']);

        $count = $pdo->query('SELECT COUNT(*) FROM call_recordings')->fetchColumn();
        $this->assertSame(1, (int)$count);
    }

    public function testInvalidSignatureRejected(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, ringover_id TEXT);");
        $pdo->exec("CREATE TABLE call_recordings (id INTEGER PRIMARY KEY AUTOINCREMENT, call_id INTEGER, file_path TEXT, file_size INTEGER, duration INTEGER, format TEXT);");

        $secret = 'abc';
        $body = json_encode(['call_id' => 'r1', 'recording_url' => 'http://e/a.mp3']);
        $sig = 'bad';

        $c = $this->container($pdo, $secret);
        $controller = new RingoverWebhookController($c, $this->request('POST', '/api/v3/webhooks/ringover/record-available', $body, $sig));
        $resp = $controller->recordAvailable();
        $this->assertSame(401, $resp->getStatusCode());
    }
}
