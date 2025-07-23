<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Logger;
use FlujosDimension\Core\Request;
use FlujosDimension\Controllers\ApiController;
use FlujosDimension\Controllers\TokenController;
use FlujosDimension\Controllers\SyncController;
use FlujosDimension\Controllers\CallsController;
use FlujosDimension\Controllers\AnalysisController;

class ApiEndpointTest extends TestCase
{
    private function makeContainer(): Container
    {
        $c = new Container();
        $c->instance('logger', new Logger(sys_get_temp_dir()));
        $c->instance('config', []);
        return $c;
    }

    private function makeRequest(string $method, string $uri, array $post = []): Request
    {
        $_GET = [];
        $_POST = $post;
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ];
        return new Request();
    }

    public function testStatusEndpoint()
    {
        $controller = new ApiController($this->makeContainer(), $this->makeRequest('GET', '/api/status'));
        $response = $controller->status();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
    }

    public function testTokenGenerateAndValidate()
    {
        $container = $this->makeContainer();
        $genController = new TokenController($container, $this->makeRequest('POST', '/api/token/generate'));
        $genResponse = $genController->generate();
        $this->assertSame(200, $genResponse->getStatusCode());
        $token = json_decode($genResponse->getContent(), true)['token'];

        $valController = new TokenController($container, $this->makeRequest('POST', '/api/token/validate', ['token' => $token]));
        $valResponse = $valController->verify();
        $this->assertTrue(json_decode($valResponse->getContent(), true)['valid']);
    }

    public function testSyncHourly()
    {
        $controller = new SyncController($this->makeContainer(), $this->makeRequest('POST', '/api/sync/hourly'));
        $response = $controller->hourly();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success']);
    }

    public function testCallsIndex()
    {
        $controller = new CallsController($this->makeContainer(), $this->makeRequest('GET', '/api/v3/calls'));
        $response = $controller->index();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testAnalysisProcess()
    {
        $controller = new AnalysisController($this->makeContainer(), $this->makeRequest('POST', '/api/v3/analysis/process'));
        $response = $controller->process();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue(isset($data['batch_id']));
    }
}
