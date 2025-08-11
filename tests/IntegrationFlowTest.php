<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Services\CallService;
use FlujosDimension\Services\CRMService;
use FlujosDimension\Infrastructure\Http\RingoverClient;
use FlujosDimension\Infrastructure\Http\PipedriveClient;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Core\Config;
use FlujosDimension\Repositories\CallRepository;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PDO;

class IntegrationFlowTest extends TestCase
{
    public function testEndToEndFlow(): void
    {
        $call = json_decode(file_get_contents(__DIR__ . '/fixtures/ringover_call.json'), true);

        // Ringover mock
        $ringoverMock = new MockHandler([
            new Response(200, [], json_encode([
                'call_list' => [$call],
                'call_list_count' => 1,
                'total_call_count' => 1,
            ])),
            new Response(200, ['Content-Length' => 5, 'X-Recording-Duration' => 30, 'X-Guzzle-Effective-Url' => 'https://files.test/call.mp3']),
            new Response(200, [], 'audio'),
        ]);
        $ringoverStack = HandlerStack::create($ringoverMock);
        $ringoverHttp = new HttpClient(['handler' => $ringoverStack]);
        $config = Config::getInstance();
        $config->set('RINGOVER_API_KEY', 't');
        $config->set('RINGOVER_API_URL', 'https://api.test');
        $ringoverClient = new RingoverClient($ringoverHttp, $config);
        $callService = new CallService($ringoverClient);

        // CRM mock
        $crmMock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['items' => [['item' => ['id' => 5]]]]])),
            new Response(200, [], json_encode(['data' => ['items' => []]])),
            new Response(200, [], json_encode(['data' => ['items' => []]])),
            new Response(201, [], json_encode(['data' => ['id' => 7]])),
        ]);
        $crmStack = HandlerStack::create($crmMock);
        $crmHttp = new HttpClient(['handler' => $crmStack]);
        $crmClient = new PipedriveClient($crmHttp, 'token');

        // DB setup
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, ringover_id TEXT, call_id TEXT, phone_number TEXT, contact_number TEXT, caller_name TEXT, contact_name TEXT, direction TEXT, status TEXT, duration INTEGER, recording_url TEXT, recording_path TEXT, has_recording INTEGER DEFAULT 0, voicemail_url TEXT, start_time TEXT, total_duration INTEGER, incall_duration INTEGER, created_at TEXT, correlation_id TEXT, batch_id TEXT, ai_transcription TEXT, pipedrive_person_id INTEGER, pipedrive_deal_id INTEGER, crm_synced INTEGER DEFAULT 0);");
        $pdo->exec("CREATE TABLE call_recordings (call_id INTEGER, file_path TEXT, file_size INTEGER, duration INTEGER, format TEXT);");
        $pdo->exec("CREATE TABLE crm_sync_logs (call_id INTEGER, result TEXT, error_message TEXT, batch_id TEXT, correlation_id TEXT, created_at TEXT);");
        $repo = new CallRepository($pdo);
        $crmService = new CRMService($crmClient, $repo);

        // Import
        $calls = iterator_to_array($callService->getCalls(new \DateTimeImmutable('2024-03-01T00:00:00Z')));
        $this->assertCount(1, $calls);
        $repo->insertOrIgnore($calls[0]);
        $id = (int)$pdo->query('SELECT id FROM calls')->fetchColumn();
        $this->assertSame(1, $id);

        // Download
        $info = $callService->downloadRecording($calls[0]['recording_url'], sys_get_temp_dir());
        $repo->addRecording($id, $info);

        // Transcription simulated
        $pdo->exec("UPDATE calls SET ai_transcription='transcribed', duration={$info['duration']} WHERE id={$id}");

        // CRM
        $dealId = $crmService->sync($id);
        $this->assertSame(7, $dealId);
        $row = $pdo->query('SELECT crm_synced, pipedrive_deal_id FROM calls WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$row['crm_synced']);
        $this->assertSame(7, (int)$row['pipedrive_deal_id']);

        @unlink($info['path']);
    }
}
