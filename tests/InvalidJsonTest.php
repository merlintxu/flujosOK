<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Logger;
use FlujosDimension\Core\Request;
use FlujosDimension\Controllers\ConfigController;

class InvalidJsonTest extends TestCase
{
    private function container(): Container
    {
        $c = new Container();
        $c->instance('logger', new Logger(sys_get_temp_dir()));
        $c->instance('config', []);
        return $c;
    }

    private function requestWithBody(string $method, string $uri, string $body): Request
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'CONTENT_TYPE' => 'application/json',
        ];
        return new Request($body);
    }

    public function testInvalidJsonReturnsBadRequest()
    {
        $controller = new ConfigController(
            $this->container(),
            $this->requestWithBody('POST', '/api/config/batch', '{invalid}')
        );
        $response = $controller->batch();
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }
}
