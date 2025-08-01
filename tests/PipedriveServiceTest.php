<?php
namespace Tests;

use FlujosDimension\Services\PipedriveService;
use FlujosDimension\Infrastructure\Http\HttpClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PipedriveServiceTest extends TestCase
{
    public function testFindPersonByPhoneRequest()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['items' => [['item' => ['id' => 42]]]]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $service = new PipedriveService($http, 't');

        $id = $service->findPersonByPhone('123');

        $this->assertSame(42, $id);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/v1/persons/search', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('123', $query['term']);
    }

    public function testFindPersonByPhoneThrowsOnError()
    {
        $mock = new MockHandler([new Response(500)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $service = new PipedriveService($http, 't');

        $this->expectException(RuntimeException::class);
        $service->findPersonByPhone('123');
    }

    public function testCreateOrUpdateDealRequest()
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['data' => ['id' => 7]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $service = new PipedriveService($http, 't');

        $id = $service->createOrUpdateDeal(['title' => 'Deal']);

        $this->assertSame(7, $id);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/v1/deals', $req->getUri()->getPath());
    }

    public function testCreateOrUpdateDealThrowsOnError()
    {
        $mock = new MockHandler([new Response(400)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $service = new PipedriveService($http, 't');

        $this->expectException(RuntimeException::class);
        $service->createOrUpdateDeal(['title' => 'Deal']);
    }
}
