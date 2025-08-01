<?php
namespace Tests;

use FlujosDimension\Services\OpenAIService;
use FlujosDimension\Core\Config;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use FlujosDimension\Infrastructure\Http\HttpClient;

class OpenAIServiceTest extends TestCase
{
    public function testChatSendsRequest()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'ok']]]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        Config::getInstance()->set('OPENAI_MODEL', 'model-x');
        $service = new OpenAIService($http, 'key');
        $result = $service->chat([['role'=>'user','content'=>'hi']], ['temperature' => 0]);
        $this->assertSame('ok', $result['choices'][0]['message']['content']);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://api.openai.com/v1/chat/completions', (string)$req->getUri());
        $this->assertSame('Bearer key', $req->getHeaderLine('Authorization'));
    }
}
