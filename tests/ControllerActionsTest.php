<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Logger;
use FlujosDimension\Controllers\CallsController;
use FlujosDimension\Controllers\TokenController;
use FlujosDimension\Controllers\ConfigController;
use FlujosDimension\Controllers\SyncController;
use FlujosDimension\Controllers\AnalysisController;
use FlujosDimension\Controllers\UserController;
use FlujosDimension\Models\Call;
use FlujosDimension\Core\Config;

class DummyLogger2 { public function error($m){} public function info($m, $c=[]){} }
class CallModelStub {
    public function paginate($p,$pp,$ob,$dir){ return ['data'=>[], 'meta'=>[]]; }
    public function findOrFail($id){ return ['id'=>$id]; }
    public function create($data){ return array_merge(['id'=>1], $data); }
    public function update($id,$data){ return array_merge(['id'=>$id], $data); }
    public function delete($id){}
}

class ControllerActionsTest extends TestCase
{
    private function container(): Container
    {
        $c = new Container();
        $c->instance('logger', new DummyLogger2());
        $c->instance('config', []);
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

    public function testCallsStoreValidationFails()
    {
        $c = $this->container();
        $c->instance(Call::class, new CallModelStub());
        $controller = new CallsController($c, $this->request('POST','/api/v3/calls', []));
        $res = $controller->store();
        $this->assertSame(400, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testCallsStoreSuccess()
    {
        $c = $this->container();
        $c->instance(Call::class, new CallModelStub());
        $controller = new CallsController($c, $this->request('POST','/api/v3/calls', [
            'phone_number'=>'123','direction'=>'inbound','status'=>'answered','duration'=>5
        ]));
        $res = $controller->store();
        $this->assertSame(201, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testTokenRevoke()
    {
        $c = $this->container();
        $controller = new TokenController($c, $this->request('POST','/api/token/revoke',['token'=>'abc']));
        $res = $controller->revoke();
        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue(json_decode($res->getContent(), true)['revoked']);
    }

    public function testTokenVerifyMissingToken()
    {
        $c = $this->container();
        $controller = new TokenController($c, $this->request('POST','/api/token/validate', []));
        $res = $controller->verify();
        $this->assertSame(400, $res->getStatusCode());
        $this->assertFalse(json_decode($res->getContent(), true)['success']);
    }

    public function testConfigUpdateMissingValue()
    {
        $c = $this->container();
        $controller = new ConfigController($c, $this->request('POST','/api/config/foo', []));
        $res = $controller->update('FOO');
        $this->assertSame(400, $res->getStatusCode());
        $this->assertFalse(json_decode($res->getContent(), true)['success']);
    }

    public function testConfigIndex()
    {
        $config = Config::getInstance();
        $config->set('FOO','bar');
        $c = $this->container();
        $controller = new ConfigController($c, $this->request('GET','/api/config'));
        $res = $controller->index();
        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('bar', $data['data']['FOO']);
    }

    public function testSyncManualNoServices()
    {
        $c = $this->container();
        $controller = new SyncController($c, $this->request('POST','/api/sync/manual'));
        $res = $controller->manual();
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(0, json_decode($res->getContent(), true)['data']['inserted']);
    }

    public function testAnalysisSentimentBatchNoService()
    {
        $c = $this->container();
        $controller = new AnalysisController($c, $this->request('POST','/api/analysis/sentiment'));
        $res = $controller->sentimentBatch();
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(0, json_decode($res->getContent(), true)['processed']);
    }

    public function testUserCreateWithoutDb()
    {
        $c = $this->container();
        $controller = new UserController($c, $this->request('POST','/api/users', [
            'username'=>'a','email'=>'a@b.c','password'=>'secret'
        ]));
        $res = $controller->create();
        $this->assertSame(201, $res->getStatusCode());
        $this->assertTrue(json_decode($res->getContent(), true)['success']);
    }
}
