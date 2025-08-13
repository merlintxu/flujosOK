<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\RateLimiter;
use PDO;

class RateLimiterTest extends TestCase
{
    public function testUsesDatabaseConfig(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE rate_limit_buckets (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket_key TEXT, tokens REAL, capacity INTEGER, last_refill INTEGER, created_at INTEGER)');
        $pdo->exec('CREATE TABLE rate_limit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket_key TEXT, action TEXT, tokens_requested INTEGER, tokens_remaining REAL, correlation_id TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE rate_limit_config (service_name TEXT UNIQUE, max_requests_per_minute INTEGER, max_requests_per_hour INTEGER, backoff_base_delay INTEGER, backoff_multiplier REAL, max_retries INTEGER, max_backoff_delay INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO rate_limit_config (service_name, max_requests_per_minute, max_requests_per_hour, backoff_base_delay, backoff_multiplier, max_retries, max_backoff_delay, created_at, updated_at) VALUES ('openai',120,1000,1,2,3,60,datetime('now'),datetime('now'))");

        $rl = new RateLimiter($pdo);
        $status = $rl->isAllowed('openai:api');
        $this->assertSame(120, $status['capacity']);
        $this->assertSame(2.0, $status['refill_rate']);
    }
}
