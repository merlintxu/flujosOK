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

    public function testGetCallsPaginationOffset()
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
        $this->assertSame('0', $params1['limit_offset']);
        $this->assertSame('100', $params1['limit_count']);
        $this->assertArrayHasKey('start_date', $params1);

        $second = $history[1]['request'];
        parse_str($second->getUri()->getQuery(), $params2);
        $this->assertSame('100', $params2['limit_offset']);
    }

    public function testGetCallsPaginationFallbackToPage()
    {
        $page1 = ['data' => array_map(fn($i) => ['id' => $i], range(1, 100))];
        $dup   = $page1; // API ignoring offset returns same data
        $page2 = ['data' => [['id' => 101]]];
        $mock = new MockHandler([
            new Response(200, [], json_encode($page1)),
            new Response(200, [], json_encode($dup)),
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
        $this->assertCount(3, $history);

        parse_str($history[0]['request']->getUri()->getQuery(), $p1);
        $this->assertSame('0', $p1['limit_offset']);

        parse_str($history[1]['request']->getUri()->getQuery(), $p2);
        $this->assertSame('100', $p2['limit_offset']);

        parse_str($history[2]['request']->getUri()->getQuery(), $p3);
        $this->assertSame('2', $p3['page']);
    }

    public function testGetCallsConvertsSinceToUtc()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
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

    public function testMapCallFieldsTransforms()
    {
        $http = new HttpClient();
        $config = $this->cfg(['RINGOVER_API_KEY' => 't']);
        $service = new RingoverService($http, $config);

        $call1 = [
            'id'             => 'abc',
            'from_number'    => '123',
            'direction'      => 'out',
            'last_state'     => 'busy',
            'incall_duration'=> 7,
            'recording_url'  => 'https://r.test/a.wav',
            'start_time'     => '2024-01-01T00:00:00Z'
        ];

        $mapped1 = $service->mapCallFields($call1);

        $this->assertSame([
            'ringover_id'    => 'abc',
            'call_id'        => null,
            'phone_number'   => '123',
            'contact_number' => null,
            'caller_name'    => null,
            'contact_name'   => null,
            'direction'      => 'outbound',
            'start_time'     => '2024-01-01T00:00:00Z',
            'total_duration' => null,
            'incall_duration'=> 7,
            'is_answered'    => null,
            'last_state'     => 'busy',
            'status'         => 'busy',
            'duration'       => 7,
            'recording_url'  => 'https://r.test/a.wav',
            'voicemail_url'  => null,
        ], $mapped1);

        $call2 = [
            'id'             => 'def',
            'to_number'      => '456',
            'direction'      => 'in',
            'is_answered'    => true,
            'total_duration' => 10,
            'recording'      => 'https://r.test/b.wav',
            'started_at'     => '2024-02-01T00:00:00Z'
        ];

        $mapped2 = $service->mapCallFields($call2);

        $this->assertSame([
            'ringover_id'    => 'def',
            'call_id'        => null,
            'phone_number'   => null,
            'contact_number' => '456',
            'caller_name'    => null,
            'contact_name'   => null,
            'direction'      => 'inbound',
            'start_time'     => '2024-02-01T00:00:00Z',
            'total_duration' => 10,
            'incall_duration'=> null,
            'is_answered'    => true,
            'last_state'     => null,
            'status'         => 'answered',
            'duration'       => 10,
            'recording_url'  => 'https://r.test/b.wav',
            'voicemail_url'  => null,
        ], $mapped2);

        $call3 = [
            'id'          => 'ghi',
            'to_number'   => '789',
            'direction'   => 'in',
            'is_answered' => false,
            'total_duration' => 0
        ];

        $mapped3 = $service->mapCallFields($call3);
        $this->assertFalse($mapped3['is_answered']);
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
        $info = $service->downloadRecording('https://files.test/rec.mp3', $dir);
        $this->assertFileExists($info['path']);
        $this->assertSame('audio', file_get_contents($info['path']));
        $this->assertSame(5, $info['size']);
        $this->assertSame('mp3', $info['format']);
        unlink($info['path']);
        rmdir($dir);
    }

    public function testDownloadRecordingUsesRedirectedFilename()
    {
        $mock = new MockHandler([
            new Response(200, [
                'Content-Length'           => 5,
                'X-Guzzle-Redirect-History'=> ['https://files.test/tmp', 'https://files.test/real.mp3']
            ]),
            new Response(200, [], 'audio')
        ]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't']);
        $service = new RingoverService($http, $config);
        $dir = sys_get_temp_dir().'/ringtest';
        $info = $service->downloadRecording('https://files.test/tmp', $dir);
        $this->assertStringEndsWith('real.mp3', $info['path']);
        $this->assertFileExists($info['path']);
        unlink($info['path']);
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
        $info = $service->downloadRecording($malicious, $dir);
        $this->assertStringStartsWith($dir, $info['path']);
        $this->assertFileExists($info['path']);
        unlink($info['path']);
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

    public function testMakeRequestRetriesWithBackoff()
    {
        $mock = new MockHandler([
            new Response(500),
            new Response(200, [], json_encode(['ok' => true]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack], 0);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $service = new RingoverService($http, $config);
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('lastRequestAt');
        $prop->setAccessible(true);
        $prop->setValue($service, microtime(true) - 1);
        $method = $ref->getMethod('makeRequest');
        $method->setAccessible(true);
        $start = microtime(true);
        $result = $method->invoke($service, 'GET', 'https://api.test/data');
        $elapsed = microtime(true) - $start;
        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $history);
        $this->assertGreaterThan(0.5, $elapsed);
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
