<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Services\CallService;
use FlujosDimension\Infrastructure\Http\RingoverClient;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;

class MapCallFieldsFixtureTest extends TestCase
{
    public function testMappingWithFixture(): void
    {
        $call = json_decode(file_get_contents(__DIR__ . '/fixtures/ringover_call.json'), true);

        $config = Config::getInstance();
        $config->set('RINGOVER_API_KEY', 'test');
        $client = new RingoverClient(new HttpClient(), $config);
        $service = new CallService($client);

        $mapped = $service->mapCallFields($call);

        $this->assertSame('abc123', $mapped['ringover_id']);
        $this->assertSame('call-xyz', $mapped['call_id']);
        $this->assertSame('+1234567890', $mapped['phone_number']);
        $this->assertSame('Alice', $mapped['caller_name']);
        $this->assertSame('Bob', $mapped['contact_name']);
        $this->assertSame('inbound', $mapped['direction']);
        $this->assertSame('answered', $mapped['status']);
        $this->assertSame(30, $mapped['duration']);
        $this->assertSame('https://files.test/call.mp3', $mapped['recording_url']);
    }
}
