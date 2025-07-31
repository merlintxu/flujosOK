<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Models\Call;
use FlujosDimension\Controllers\PaginationController;
use PDO;

class OrderBySanitizationTest extends TestCase
{
    private Container $container;
    private Call $model;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ringover_id TEXT,
            phone_number TEXT,
            direction TEXT,
            status TEXT,
            duration INTEGER,
            recording_url TEXT,
            ai_transcription TEXT,
            ai_summary TEXT,
            ai_keywords TEXT,
            ai_sentiment TEXT,
            pipedrive_contact_id INTEGER,
            pipedrive_deal_id INTEGER,
            created_at TEXT,
            updated_at TEXT
        )");
        $pdo->exec("INSERT INTO calls (ringover_id, phone_number, direction, status, duration, created_at, updated_at) VALUES ('r1','111','inbound','answered',10,'2024-01-01 00:00:00','2024-01-01 00:00:00')");
        $pdo->exec("INSERT INTO calls (ringover_id, phone_number, direction, status, duration, created_at, updated_at) VALUES ('r2','222','outbound','missed',20,'2024-01-02 00:00:00','2024-01-02 00:00:00')");

        $this->container = new Container();
        $this->container->instance('database', $pdo);
        $this->container->instance('logger', new class { public function info(...$a){} public function error(...$a){} });
        $this->model = new Call($this->container);
    }

    public function testPaginateRejectsInvalidOrderBy(): void
    {
        $result = $this->model->paginate(1, 10, 'duration; DROP TABLE calls', 'INVALID');
        $this->assertCount(2, $result['data']);
        $this->assertSame(2, $this->model->count());
    }

    public function testSearchRejectsOrderByInjection(): void
    {
        $result = $this->model->search(['order_by' => 'status; DROP', 'direction_sort' => 'DOWN']);
        $this->assertCount(2, $result['data']);
        $this->assertSame(2, $this->model->count());
    }

    public function testGetPaginationParamsSanitizesInput(): void
    {
        $this->container->instance('config', []);
        $_GET = ['order_by' => 'id;DELETE', 'direction' => 'foo'];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $request = new Request();
        $controller = new PaginationController($this->container, $request);
        $params = $controller->params();
        $this->assertSame('created_at', $params['order_by']);
        $this->assertSame('DESC', $params['direction']);
    }
}
