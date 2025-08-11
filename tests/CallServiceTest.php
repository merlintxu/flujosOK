<?php
namespace Tests;

use FlujosDimension\Services\CallService;
use FlujosDimension\Infrastructure\Http\RingoverClient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;
use FlujosDimension\Infrastructure\Http\RecordingTooLargeException;

class CallServiceTest extends TestCase
{
    private function cfg(array $vals): Config
    {
        $config = Config::getInstance();
        foreach ($vals as $k => $v) {
            $config->set($k, $v);
        }
        return $config;
    }

    public function testGetCallsPaginationByPage()
    {
        $page1 = [
            'call_list' => array_map(fn($i) => ['cdr_id' => $i], range(1, 100)),
            'call_list_count' => 100,
            'total_call_count' => 101,
        ];
        $page2 = [
            'call_list' => [['cdr_id' => 101]],
            'call_list_count' => 1,
            'total_call_count' => 101,
        ];
        $mock = new MockHandler([
            new Response(200, [], json_encode($page1)),
            new Response(200, [], json_encode($page2)),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
        $calls = iterator_to_array($service->getCalls(new DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertCount(101, $calls);
        $this->assertCount(2, $history);

        parse_str($history[0]['request']->getUri()->getQuery(), $p1);
        $this->assertSame('1', $p1['page']);
        $this->assertSame('100', $p1['limit']);

        parse_str($history[1]['request']->getUri()->getQuery(), $p2);
        $this->assertSame('2', $p2['page']);
    }

    public function testGetCallsConvertsSinceToUtc()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['call_list' => [], 'total_call_count' => 0, 'call_list_count' => 0]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);

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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);

        $call1 = [
            'cdr_id'         => 'abc',
            'from_number'    => '123',
            'direction'      => 'out',
            'last_state'     => 'busy',
            'incall_duration'=> 7,
            'record'         => 'https://r.test/a.wav',
            'call_start'     => '2024-01-01T00:00:00Z'
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
            'status'         => 'busy',
            'duration'       => 7,
            'recording_url'  => 'https://r.test/a.wav',
            'voicemail_url'  => null,
        ], $mapped1);

        $call2 = [
            'cdr_id'         => 'def',
            'contact_number' => '456',
            'direction'      => 'in',
            'is_answered'    => true,
            'total_duration' => 10,
            'record'         => 'https://r.test/b.wav',
            'call_start'     => '2024-02-01T00:00:00Z'
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
            'status'         => 'answered',
            'duration'       => 10,
            'recording_url'  => 'https://r.test/b.wav',
            'voicemail_url'  => null,
        ], $mapped2);

        $call3 = [
            'cdr_id'       => 'ghi',
            'contact_number' => '789',
            'direction'   => 'in',
            'is_answered' => false,
            'total_duration' => 0
        ];

        $mapped3 = $service->mapCallFields($call3);
        $this->assertSame('missed', $mapped3['status']);
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
        $dir = sys_get_temp_dir().'/ringtest';
        $info = $service->downloadRecording('https://files.test/rec.mp3', $dir);
        $this->assertFileExists($info['path']);
        $this->assertSame('audio', file_get_contents($info['path']));
        $this->assertSame(5, $info['size']);
        $this->assertSame('mp3', $info['format']);
        unlink($info['path']);
        rmdir($dir);
    }

    public function testDownloadRecordingWithAbsolutePath(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Length' => 3]),
            new Response(200, [], 'foo')
        ]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't']);
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
        $dir = sys_get_temp_dir() . '/ringabs';
        $info = $service->downloadRecording('https://files.test/abs.mp3', $dir);
        $this->assertStringStartsWith($dir, $info['path']);
        $this->assertFileExists($info['path']);
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
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
        $client = new RingoverClient($http, $config);
        $ref = new \ReflectionClass($client);
        $prop = $ref->getProperty('lastRequestAt');
        $prop->setAccessible(true);
        $prop->setValue($client, microtime(true) - 1);
        $method = $ref->getMethod('request');
        $method->setAccessible(true);
        $start = microtime(true);
        $result = $method->invoke($client, 'GET', 'https://api.test/data');
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
        $result = $service->testConnection();
        $this->assertTrue($result['success']);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('GET', $req->getMethod());
    }

    public function testTestConnectionFailure()
    {
        $mock = new MockHandler([new Response(500)]);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient(['handler' => $stack]);
        $config = $this->cfg(['RINGOVER_API_KEY' => 't', 'RINGOVER_API_URL' => 'https://api.test']);
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
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
        $client = new RingoverClient($http, $config);
        $service = new CallService($client);
        $service->testConnection();

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('api.config-test', $request->getUri()->getHost());
        $this->assertSame('/calls', $request->getUri()->getPath());
        $this->assertSame('secret-token', $request->getHeaderLine('Authorization'));
    }
}
