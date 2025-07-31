<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Controllers\WebhookController;
use FlujosDimension\Models\Webhook;
use PDO;

class WebhookControllerTest extends TestCase
{
    private function container(PDO $pdo): Container
    {
        $c = new Container();
        $c->instance('logger', new class { public function error(...$a){} public function info(...$a){} });
        $c->instance('config', []);
        $c->instance('database', $pdo);
        return $c;
    }

    private function request(string $method, string $uri, array $post = []): Request
    {
        $_GET = [];
        $_POST = $post;
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ];
        return new Request();
    }

    public function testCreateWebhookSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                event TEXT NOT NULL,
                created_at TEXT
            )"
        );

        $c = $this->container($pdo);
        $model = new Webhook($c);
        $c->instance(Webhook::class, $model);

        $controller = new WebhookController(
            $c,
            $this->request('POST', '/api/webhooks', [
                'url' => 'https://example.com/hook',
                'event' => 'call.finished'
            ])
        );

        $response = $controller->create();
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('https://example.com/hook', $data['data']['url']);
        $count = $pdo->query('SELECT COUNT(*) FROM webhooks')->fetchColumn();
        $this->assertSame(1, (int)$count);
    }

    public function testCreateWebhookInvalidUrl(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                event TEXT NOT NULL,
                created_at TEXT
            )"
        );

        $c = $this->container($pdo);
        $model = new Webhook($c);
        $c->instance(Webhook::class, $model);

        $controller = new WebhookController(
            $c,
            $this->request('POST', '/api/webhooks', [
                'url' => 'notaurl',
                'event' => 'call.started'
            ])
        );

        $response = $controller->create();
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }
}
