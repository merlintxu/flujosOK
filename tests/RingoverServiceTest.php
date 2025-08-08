<?php
namespace Tests;

use FlujosDimension\Services\RingoverService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;
use FlujosDimension\Services\RecordingTooLargeException;

class RingoverServiceTest extends TestCase
{
    private function cfg(array $vals): Config
    {
        $config = Config::getInstance();
        foreach ($vals as $k => $v) {
            $config->set($k, $v);
        }
        return $config;
    }

    public function testGetCallsPagination()
    {
        $page1 = ['data' => array_map(fn($i) => ['id' => $i], range(1, 100))];
        $page2 = ['data' => [['id' => 101]]];
        $mock = new MockHandler([
            new Response(200, [], json_encode($page1)),
            new Response(200, [], json_encode($page2))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $calls = iterator_to_array($service->getCalls(new DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertCount(101, $calls);
        $this->assertCount(2, $history);

        $first = $history[0]['request'];
        parse_str($first->getUri()->getQuery(), $params1);
        $this->assertSame('1', $params1['page']);
        $this->assertSame('100', $params1['limit']);
        $this->assertArrayHasKey('start_date', $params1);

        $second = $history[1]['request'];
        parse_str($second->getUri()->getQuery(), $params2);
        $this->assertSame('2', $params2['page']);
    }

    public function testGetCallsConvertsSinceToUtc()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);

        $since = new DateTimeImmutable('2024-01-01 01:30:00', new \DateTimeZone('Europe/Madrid'));
        iterator_to_array($service->getCalls($since));

        $req = $history[0]['request'];
        parse_str($req->getUri()->getQuery(), $params);
        $this->assertSame('2024-01-01T00:30:00+00:00', $params['start_date']);
    }

    public function testDownloadRecording()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Length' => 5]),
            new Response(200, [], 'audio')
        ]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't']);
        $service = new RingoverService($http, $config);
        $dir = sys_get_temp_dir().'/ringtest';
        $path = $service->downloadRecording('https://files.test/rec.mp3', $dir);
        $this->assertFileExists($path);
        $this->assertSame('audio', file_get_contents($path));
        unlink($path);
        rmdir($dir);
    }

    public function testDownloadRecordingSanitizesPath()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Length' => 5]),
            new Response(200, [], 'audio')
        ]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't']);
        $service = new RingoverService($http, $config);
        $dir = sys_get_temp_dir().'/ringtest';
        $malicious = 'https://files.test/..%2Fsecret/evil.mp3';
        $path = $service->downloadRecording($malicious, $dir);
        $this->assertStringStartsWith($dir, $path);
        $this->assertFileExists($path);
        unlink($path);
        rmdir($dir);
    }

    public function testDownloadRecordingHonorsSizeLimit()
    {
        $size = 1024 * 1024 + 1;
        $mock = new MockHandler([
            new Response(200, ['Content-Length' => $size])
        ]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg([
            'RINGOVER_API_KEY' => 't',
            'RINGOVER_MAX_RECORDING_MB' => 1
        ]);
        $service = new RingoverService($http, $config);
        $dir = sys_get_temp_dir().'/ringtest';

        try {
            $service->downloadRecording('https://files.test/big.mp3', $dir);
            $this->fail('Expected exception not thrown');
        } catch (RecordingTooLargeException $e) {
            $this->assertStringContainsString('size', $e->getMessage());
        }

        $file = $dir.'/big.mp3';
        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    public function testTestConnectionSuccess()
    {
        $mock = new MockHandler([new Response(200)]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $result = $service->testConnection();
        $this->assertTrue($result['success']);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('HEAD', $req->getMethod());
    }

    public function testTestConnectionFailure()
    {
        $mock = new MockHandler([new Response(500)]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $result = $service->testConnection();
        $this->assertFalse($result['success']);
    }

    public function testUsesConfiguredUrlAndKey()
    {
        $mock = new MockHandler([new Response(200)]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);

        $config = $this->cfg([
            'RINGOVER_API_KEY' => 'secret-token',
            'RINGOVER_API_URL'   => 'https://api.config-test'
        ]);

        $service = new RingoverService($http, $config);
        $service->testConnection();

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('api.config-test', $request->getUri()->getHost());
        $this->assertSame('/calls', $request->getUri()->getPath());
        $this->assertSame('secret-token', $request->getHeaderLine('Authorization'));
    }
}
