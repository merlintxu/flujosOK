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
use RuntimeException;

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
        $mock = new MockHandler([
            new Response(200, ['Link' => '<https://api.test/calls?page=2>; rel="next"'], json_encode(['data' => [['id' => 1]]])),
            new Response(200, [], json_encode(['data' => [['id' => 2]]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_TOKEN' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $calls = iterator_to_array($service->getCalls(new DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertCount(2, $calls);
        $this->assertCount(2, $history);
        $first = $history[0]['request'];
        $this->assertSame('GET', $first->getMethod());
        $this->assertStringContainsString('date_start', $first->getUri()->getQuery());
    }

    public function testDownloadRecording()
    {
        $mock = new MockHandler([new Response(200, [], 'audio')]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_TOKEN' => 't']);
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
        $mock = new MockHandler([new Response(200, [], 'audio')]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_TOKEN' => 't']);
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
        $data = str_repeat('a', 1024 * 1024 + 1);
        $mock = new MockHandler([new Response(200, [], $data)]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg([
            'RINGOVER_API_TOKEN' => 't',
            'RINGOVER_MAX_RECORDING_MB' => 1
        ]);
        $service = new RingoverService($http, $config);
        $dir = sys_get_temp_dir().'/ringtest';

        try {
            $service->downloadRecording('https://files.test/big.mp3', $dir);
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
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
        $config = $this->cfg(['RINGOVER_API_TOKEN' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
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
        $config = $this->cfg(['RINGOVER_API_TOKEN' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $result = $service->testConnection();
        $this->assertFalse($result['success']);
    }
}
