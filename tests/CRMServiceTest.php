<?php
namespace Tests;

use FlujosDimension\Infrastructure\Http\PipedriveClient;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Services\CRMService;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use PDO;
use FlujosDimension\Repositories\CallRepository;

class CRMServiceTest extends TestCase
{
    public function testFindPersonByPhoneRequest()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['items' => [['item' => ['id' => 42]]]]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $client  = new PipedriveClient($http, 't');
        $service = new CRMService($client);

        $id = $service->findPersonByPhone('123');

        $this->assertSame(42, $id);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/v1/persons/search', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('123', $query['term']);
    }

    public function testFindPersonByPhoneThrowsOnError()
    {
        $mock = new MockHandler([new Response(500)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $client  = new PipedriveClient($http, 't');
        $service = new CRMService($client);

        $this->expectException(RuntimeException::class);
        $service->findPersonByPhone('123');
    }

    public function testCreateOrUpdateDealRequest()
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['data' => ['id' => 7]]))
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $client  = new PipedriveClient($http, 't');
        $service = new CRMService($client);

        $id = $service->createOrUpdateDeal(['title' => 'Deal']);

        $this->assertSame(7, $id);
        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/v1/deals', $req->getUri()->getPath());
    }

    public function testCreateOrUpdateDealThrowsOnError()
    {
        $mock = new MockHandler([new Response(400)]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $client  = new PipedriveClient($http, 't');
        $service = new CRMService($client);

        $this->expectException(RuntimeException::class);
        $service->createOrUpdateDeal(['title' => 'Deal']);
    }

    public function testSyncCreatesDealAndLogs(): void
    {
        $mock = new MockHandler([
            // findPersonByPhone
            new Response(200, [], json_encode(['data' => ['items' => [['item' => ['id' => 5]]]]])),
            // findOpenDeal by call ID
            new Response(200, [], json_encode(['data' => ['items' => []]])),
            // findOpenDeal by phone
            new Response(200, [], json_encode(['data' => ['items' => []]])),
            // createOrUpdateDeal
            new Response(201, [], json_encode(['data' => ['id' => 7]])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new HttpClient(['handler' => $stack]);
        $client  = new PipedriveClient($http, 't');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone_number TEXT,
            ai_summary TEXT,
            ai_sentiment TEXT,
            ai_transcription TEXT,
            recording_url TEXT,
            duration INTEGER,
            pipedrive_person_id INTEGER,
            pipedrive_deal_id INTEGER,
            crm_synced INTEGER DEFAULT 0
        );");
        $pdo->exec("INSERT INTO calls (id, phone_number, ai_summary, ai_sentiment, ai_transcription, recording_url, duration)
            VALUES (1,'123','s','pos','t','http://r',30)");
        $pdo->exec("CREATE TABLE crm_sync_logs (
            call_id INTEGER,
            result TEXT,
            error_message TEXT,
            batch_id TEXT,
            correlation_id TEXT,
            created_at TEXT
        );");

        $repo = new CallRepository($pdo);
        $service = new CRMService($client, $repo);

        $dealId = $service->sync(1, 'batch-a', 'corr-a');

        $this->assertSame(7, $dealId);
        $row = $pdo->query('SELECT pipedrive_person_id, pipedrive_deal_id, crm_synced FROM calls WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(5, (int)$row['pipedrive_person_id']);
        $this->assertSame(7, (int)$row['pipedrive_deal_id']);
        $this->assertSame(1, (int)$row['crm_synced']);
        $log = $pdo->query('SELECT result, batch_id, correlation_id FROM crm_sync_logs')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('success', $log['result']);
        $this->assertSame('batch-a', $log['batch_id']);
        $this->assertSame('corr-a', $log['correlation_id']);
        $this->assertCount(4, $history);
    }
}
