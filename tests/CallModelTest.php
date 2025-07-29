<?php
namespace Tests;

use FlujosDimension\Models\Call;
use FlujosDimension\Core\Container;
use PDO;
use PHPUnit\Framework\TestCase;

class CallModelTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE calls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ringover_id TEXT,
                phone_number TEXT,
                direction TEXT,
                status TEXT,
                duration INTEGER,
                recording_url TEXT,
                ai_transcription TEXT,
                ai_summary TEXT,
                ai_sentiment TEXT,
                pipedrive_contact_id INTEGER,
                pipedrive_deal_id INTEGER,
                created_at TEXT,
                updated_at TEXT
            )"
        );

        $this->container = new Container();
        $this->container->instance('database', $pdo);
        $this->container->instance('logger', new class { public function info(...$a){} public function error(...$a){} });
    }

    public function testPersistPipedriveDealId(): void
    {
        $callModel = new Call($this->container);

        $created = $callModel->create([
            'ringover_id' => 'r1',
            'phone_number' => '123456',
            'direction' => 'inbound',
            'status' => 'answered',
            'duration' => 30,
            'pipedrive_contact_id' => 11,
            'pipedrive_deal_id' => 22
        ]);

        $this->assertSame(22, $created['pipedrive_deal_id']);
        $this->assertIsInt($created['pipedrive_deal_id']);

        $found = $callModel->find($created['id']);
        $this->assertSame(22, $found['pipedrive_deal_id']);
        $this->assertIsInt($found['pipedrive_deal_id']);
    }
}
