<?php
namespace Tests;

use FlujosDimension\Services\OpenAIService;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OpenAIServiceErrorTest extends TestCase
{
    public function testChatThrowsOnHttpError()
    {
        $mock = new MockHandler([new Response(500)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        Config::getInstance()->set('OPENAI_MODEL', 'model-x');
        $service = new OpenAIService($http, 'key');

        $this->expectException(RuntimeException::class);
        $service->chat([['role' => 'user', 'content' => 'hi']]);
    }
}
