<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Config;
use FlujosDimension\Core\JWT;
use FlujosDimension\Controllers\AuthRequiredController;
use PDO;


class DummyLogger3 { public function error($m){} public function info($m,$c=[]){} }

class AuthRequirementTest extends TestCase
{
    public function testControllerRequiresValidToken()
    {
        $config = Config::getInstance();
        $config->set('JWT_KEYS_CURRENT', 'secret');
        $config->set('JWT_KID', 'test');
        $config->set('JWT_EXPIRATION_HOURS', '1');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_hash TEXT UNIQUE,
                name TEXT,
                expires_at TEXT,
                last_used_at TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $container = new Container();
        $container->instance('logger', new DummyLogger3());
        $container->instance('config', []);
        $container->instance(PDO::class, $pdo);
        $container->alias(PDO::class, 'database');
        $container->singleton(JWT::class, fn($c) => new JWT($c->resolve(PDO::class)));
        $container->alias(JWT::class, 'jwtService');

        /** @var JWT $jwt */
        $jwt = $container->resolve('jwtService');
        $token = $jwt->generateToken(['user_id' => 99]);

        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/secure',
            'HTTP_AUTHORIZATION' => "Bearer $token"
        ];
        $request = new Request();

        $controller = new AuthRequiredController($container, $request);
        $response = $controller->secure();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(99, $data['user_id']);
    }
}
