<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Controllers\DashboardController;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;
use FlujosDimension\Services\AnalyticsService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Services\OpenAIService;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Infrastructure\Http\HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class DashboardDbStub {
    private array $results; private int $i = 0;
    public function __construct(array $results) { $this->results = $results; }
    public function prepare($sql) { return new class($this->results[$this->i++]) {
        private array $r; public function __construct($r){$this->r=$r;} public function execute($p=[]){} public function fetch(){return $this->r;}
    }; }
    public function query($sql) { return new class($this->results[$this->i++]) {
        private array $r; public function __construct($r){$this->r=$r;} public function fetchAll(){return $this->r;} public function fetch(){return $this->r;}
    }; }
}
class DummyLogger { public function error($m){} }

class DummyRingover extends RingoverService {
    public function __construct() {}
}

class DashboardControllerTest extends TestCase
{
    public function testQuickStatsReturnsJson()
    {
        $container = new Container();
        $db = new DashboardDbStub([
            ['calls_today'=>5,'answered_today'=>3,'calls_last_hour'=>2,'avg_duration_today'=>30],
            ['calls_yesterday'=>4,'answered_yesterday'=>2]
        ]);
        $container->instance('logger', new DummyLogger());
        $container->instance('config', []);
        $container->instance('database', $db);
        $pdo = new \PDO('sqlite::memory:');
        $repo = new CallRepository($pdo);
        $openai = new OpenAIService(new HttpClient(['handler' => HandlerStack::create(new MockHandler())]), 'k');
        $analytics = new AnalyticsService($repo, $openai);
        $container->instance('analyticsService', $analytics);
        $container->instance('ringoverService', new DummyRingover());
        $_GET = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $controller = new DashboardController($container, new Request());
        $response = $controller->quickStats();
        $this->assertInstanceOf(Response::class, $response);
        $ref = new \ReflectionClass($response);
        $prop = $ref->getProperty('content');
        $prop->setAccessible(true);
        $data = json_decode($prop->getValue($response), true);
        $this->assertTrue($data['success']);
        $this->assertSame(5, $data['data']['today']['total_calls']);
    }
}
