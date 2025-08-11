<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Config;
use FlujosDimension\Core\JWT;
use FlujosDimension\Controllers\N8nController;
use PDO;

class DummyLogger4 { public function error($m,$c=[]){} public function info($m,$c=[]){} }

class N8nControllerTest extends TestCase
{
    private function makeContainer(): Container
    {
        $config = Config::getInstance();
        $config->set('JWT_SECRET', 'secret');
        $config->set('JWT_EXPIRATION_HOURS', '1');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, token_hash TEXT UNIQUE, name TEXT, expires_at TEXT, last_used_at TEXT, is_active BOOLEAN DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $c = new Container();
        $c->instance('logger', new DummyLogger4());
        $c->instance('config', []);
        $c->instance(PDO::class, $pdo);
        $c->alias(PDO::class, 'database');
        $c->singleton(JWT::class, fn($c) => new JWT($c->resolve(PDO::class)));
        $c->alias(JWT::class, 'jwtService');

        return $c;
    }

    private function makeRequest(?string $token = null): Request
    {
        $_GET = $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v3/calls/1/summary',
        ];
        if ($token !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        return new Request();
    }

    public function testSummaryResponseStructure(): void
    {
        $c = $this->makeContainer();
        $c->instance('callRepository', new class {
            public function find(int $id) {
                return [
                    'id' => $id,
                    'ai_summary' => 'Resumen de prueba',
                    'direction' => 'inbound',
                    'status' => 'completed',
                    'duration' => 120,
                    'start_time' => '2024-01-01 10:00:00',
                    'ai_sentiment' => 'positive',
                    'ai_keywords' => 'foo,bar',
                    'recording_url' => 'http://example.com/rec.mp3',
                    'voicemail_url' => null,
                ];
            }
        });

        /** @var JWT $jwt */
        $jwt = $c->resolve('jwtService');
        $token = $jwt->generateToken(['user_id' => 1]);

        $controller = new N8nController($c, $this->makeRequest($token));
        $response = $controller->getSummary('1');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('call_id', $data['data']);
        $this->assertArrayHasKey('summary', $data['data']);
        $this->assertArrayHasKey('metadata', $data['data']);
        $this->assertArrayHasKey('insights', $data['data']);
        $this->assertArrayHasKey('recordings', $data['data']);
    }

    public function testSummaryReturns404WhenCallMissing(): void
    {
        $c = $this->makeContainer();
        $c->instance('callRepository', new class {
            public function find(int $id) { return null; }
        });

        /** @var JWT $jwt */
        $jwt = $c->resolve('jwtService');
        $token = $jwt->generateToken(['user_id' => 1]);

        $controller = new N8nController($c, $this->makeRequest($token));
        $response = $controller->getSummary('99');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSummaryRequiresValidToken(): void
    {
        $c = $this->makeContainer();
        $c->instance('callRepository', new class {
            public function find(int $id) { return ['id' => $id]; }
        });

        $controller = new N8nController($c, $this->makeRequest());
        $response = $controller->getSummary('1');
        $this->assertSame(401, $response->getStatusCode());
    }
}
