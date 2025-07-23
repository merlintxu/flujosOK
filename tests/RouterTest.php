<?php
namespace Tests;

use FlujosDimension\Core\Router;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/Fixtures/RouteController.php';


class RouterTest extends TestCase
{
    public function testDispatchMatchesRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hi/Alice';
        $router = new Router(new \FlujosDimension\Core\Container());
        $router->get('/hi/{name}', 'RouteController@greet');
        $response = $router->dispatch(new Request());
        $this->assertInstanceOf(Response::class, $response);
        $ref = new \ReflectionClass($response);
        $prop = $ref->getProperty('content');
        $prop->setAccessible(true);
        $this->assertSame('Hello Alice', $prop->getValue($response));
    }
}
