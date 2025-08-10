<?php
namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use FlujosDimension\Repositories\CallRepository;

class CallRepositoryTest extends TestCase
{
    private function repo(PDO $pdo): CallRepository
    {
        return new CallRepository($pdo);
    }

    public function testInsertOrIgnoreStoresMappedFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ringover_id TEXT UNIQUE,
            call_id TEXT,
            phone_number TEXT,
            contact_number TEXT,
            caller_name TEXT,
            contact_name TEXT,
            direction TEXT,
            status TEXT,
            duration INTEGER,
            recording_url TEXT,
            voicemail_url TEXT,
            start_time TEXT,
            total_duration INTEGER,
            incall_duration INTEGER,
            created_at TEXT,
            recording_path TEXT,
            has_recording INTEGER DEFAULT 0
        );");

        $repo = $this->repo($pdo);
        $data = [
            'ringover_id'    => 'r1',
            'call_id'        => 'c1',
            'phone_number'   => '123',
            'contact_number' => '456',
            'caller_name'    => 'Alice',
            'contact_name'   => 'Bob',
            'direction'      => 'inbound',
            'status'         => 'answered',
            'duration'       => 8,
            'recording_url'  => 'http://rec',
            'voicemail_url'  => 'http://vm',
            'start_time'     => '2024-01-01 00:00:00',
            'total_duration' => 10,
            'incall_duration'=> 8,
        ];

        $inserted = $repo->insertOrIgnore($data);
        $this->assertSame(1, $inserted);

        $row = $pdo->query("SELECT * FROM calls WHERE ringover_id='r1'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('c1', $row['call_id']);
        $this->assertSame('456', $row['contact_number']);
        $this->assertSame('Alice', $row['caller_name']);
        $this->assertSame('Bob', $row['contact_name']);
        $this->assertSame('inbound', $row['direction']);
        $this->assertSame('answered', $row['status']);
        $this->assertSame('http://rec', $row['recording_url']);
        $this->assertSame('http://vm', $row['voicemail_url']);
        $this->assertSame('2024-01-01 00:00:00', $row['start_time']);
        $this->assertSame(10, (int)$row['total_duration']);
        $this->assertSame(8, (int)$row['incall_duration']);
    }

    public function testAddRecordingInsertsAndUpdates(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ringover_id TEXT,
            recording_url TEXT,
            recording_path TEXT,
            has_recording INTEGER DEFAULT 0
        );");
        $pdo->exec("CREATE TABLE call_recordings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            call_id INTEGER,
            file_path TEXT,
            file_size INTEGER,
            duration INTEGER,
            format TEXT
        );");
        $pdo->exec("INSERT INTO calls (id, ringover_id) VALUES (1, 'r1')");

        $repo = $this->repo($pdo);
        $repo->addRecording(1, [
            'url' => 'http://example.com/a.mp3',
            'path' => '/tmp/a.mp3',
            'size' => 123,
            'duration' => 7,
            'format' => 'mp3',
        ]);

        $call = $pdo->query('SELECT recording_url, recording_path, has_recording FROM calls WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('http://example.com/a.mp3', $call['recording_url']);
        $this->assertSame('/tmp/a.mp3', $call['recording_path']);
        $this->assertSame(1, (int)$call['has_recording']);

        $rec = $pdo->query('SELECT file_path, file_size, duration, format FROM call_recordings WHERE call_id=1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('/tmp/a.mp3', $rec['file_path']);
        $this->assertSame(123, (int)$rec['file_size']);
        $this->assertSame(7, (int)$rec['duration']);
        $this->assertSame('mp3', $rec['format']);
    }

    public function testSetPendingAnalysisMarksCall(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pending_analysis INTEGER DEFAULT 0
        );");
        $pdo->exec("INSERT INTO calls (id, pending_analysis) VALUES (1, 0)");

        $repo = $this->repo($pdo);
        $repo->setPendingAnalysis(1, true);

        $value = $pdo->query('SELECT pending_analysis FROM calls WHERE id=1')->fetchColumn();
        $this->assertSame(1, (int)$value);
    }

    public function testPendingReturnsOnlyCallsWithRecording(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pending_analysis INTEGER DEFAULT 0,
            has_recording INTEGER DEFAULT 0,
            recording_path TEXT,
            created_at TEXT
        );");
        $pdo->exec("INSERT INTO calls (id,pending_analysis,has_recording,recording_path,created_at) VALUES
            (1,1,1,'/tmp/a.mp3','2024-01-01 00:00:00'),
            (2,1,0,'/tmp/b.mp3','2024-01-02 00:00:00'),
            (3,1,1,'','2024-01-03 00:00:00'),
            (4,0,1,'/tmp/d.mp3','2024-01-04 00:00:00')
        ");

        $repo = $this->repo($pdo);
        $pending = $repo->pending();

        $this->assertCount(1, $pending);
        $this->assertSame(1, (int)$pending[0]['id']);
    }
}
