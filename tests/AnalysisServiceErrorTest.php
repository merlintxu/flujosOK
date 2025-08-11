<?php
namespace Tests;

use FlujosDimension\Infrastructure\Http\OpenAIClient;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use FlujosDimension\Services\AnalysisService;

class AnalysisServiceErrorTest extends TestCase
{
    public function testChatThrowsOnHttpError()
    {
        $mock = new MockHandler([new Response(500)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        Config::getInstance()->set('OPENAI_MODEL', 'model-x');
        $client  = new OpenAIClient($http, 'key');
        $service = new AnalysisService($client);

        $this->expectException(RuntimeException::class);
        $service->chat([['role' => 'user', 'content' => 'hi']]);
    }
}
