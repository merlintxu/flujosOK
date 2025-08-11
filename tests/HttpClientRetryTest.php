<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Infrastructure\Http\HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class HttpClientRetryTest extends TestCase
{
    public function testRetriesOnServerError(): void
    {
        $mock = new MockHandler([
            new Response(500),
            new Response(200, [], 'ok'),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new HttpClient(['handler' => $stack], 5, 1);

        $resp = $client->request('GET', 'https://example.com');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertCount(2, $history);
    }
}
