<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Config;
use FlujosDimension\Core\Database;
use FlujosDimension\Core\JWT;
use FlujosDimension\Controllers\AuthRequiredController;

class TokenDbStub2 {
    public function insert($q,$p=[]) {}
    public function selectOne($q,$p=[]) { return ['id'=>1]; }
    public function update($q,$p=[]) { return 1; }
    public function delete($q,$p=[]) {}
    public function select($q,$p=[]) { return []; }
}

class DummyLogger3 { public function error($m){} public function info($m,$c=[]){} }

class AuthRequirementTest extends TestCase
{
    public function testControllerRequiresValidToken()
    {
        $config = Config::getInstance();
        $config->set('JWT_SECRET', 'secret');
        $config->set('JWT_EXPIRATION_HOURS', '1');

        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, new TokenDbStub2());

        $container = new Container();
        $container->instance('logger', new DummyLogger3());
        $container->instance('config', []);
        $container->singleton(JWT::class, fn() => new JWT());
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
