<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Container;
use FlujosDimension\Services\PipedriveService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Services\RingoverService;

class AdminApiTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'secret';
        $this->container = new Container();

        $this->container->instance('analyticsService', new class {
            private int $call = 0;
            public function processBatch(int $n): void { $this->call++; }
            public function lastProcessed(): int { return $this->call === 1 ? 5 : 0; }
        });

        $this->container->instance('callRepository', new class {
            public function callsNotInCrm() { return [['id'=>1,'phone_number'=>'123']]; }
            public function markCrmSynced($id,$dealId) {}
            public function insertOrIgnore($call) {}
        });

        $this->container->instance(PipedriveService::class, new class {
            public function findPersonByPhone($p){ return 1; }
            public function createOrUpdateDeal($d){ return 7; }
        });
        $this->container->alias(PipedriveService::class, 'pipedriveService');

        $this->container->instance(RingoverService::class, new class {
            public function getCalls($since){ return [['recording_url'=>null]]; }
            public function downloadRecording($url){}
        });
        $this->container->alias(RingoverService::class, 'ringoverService');

        $this->container->instance('jwtService', new class {
            public function generateToken(array $payload){ return 'tok123'; }
        });

        $this->container->instance(\PDO::class, new class {
            public function prepare($sql){ return new class { public function execute($a=[]){} }; }
        });
        $this->container->alias(\PDO::class, 'database');
    }

    private function runScript(string $name, array $post = [], bool $goodCsrf = true): array
    {
        $_GET = [];
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/api/'.$name;
        if (!defined('FD_TESTING')) {
            define('FD_TESTING', true);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = ['authenticated' => true, 'csrf_token' => 'tok'];
        if ($goodCsrf) {
            $_POST['csrf_token'] = 'tok';
        } else {
            $_POST['csrf_token'] = 'bad';
        }
        $container = $this->container;
        ob_start();
        try {
            include __DIR__.'/../admin/api/'.$name;
        } catch (\RuntimeException $e) {
            // handled via respond_error in testing mode
        }
        $output = ob_get_clean();
        $code = http_response_code();
        if ($code === false) {
            $code = 200;
        }
        http_response_code(200);
        return ['data' => json_decode($output, true), 'code' => $code];
    }

    public function testGenerateTokenSuccess()
    {
        $r = $this->runScript('generate_token.php', ['token_name' => 'A', 'duration' => '1hour']);
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['data']['success']);
        $this->assertNotEmpty($r['data']['token']['token']);
    }

    public function testGenerateTokenValidation()
    {
        $r = $this->runScript('generate_token.php', ['duration' => '1hour']);
        $this->assertSame(400, $r['code']);
        $this->assertFalse($r['data']['success']);
    }

    public function testSyncRingoverSuccess()
    {
        $r = $this->runScript('sync_ringover.php', ['download' => '1']);
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['data']['success']);
    }

    public function testBatchOpenaiSuccess()
    {
        $r = $this->runScript('batch_openai.php');
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['data']['success']);
        $this->assertSame(5, $r['data']['processed']);
    }
}
