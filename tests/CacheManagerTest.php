<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\CacheManager;

class CacheManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cachetest_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*.cache') ?: []);
            rmdir($this->dir);
        }
    }

    public function testDeletePatternRemovesFiles(): void
    {
        $cache = new CacheManager($this->dir);
        $cache->set('a', 1);
        $cache->set('b', 2);

        $this->assertCount(2, glob($this->dir . '/*.cache'));

        $cache->deletePattern('*');

        $this->assertCount(0, glob($this->dir . '/*.cache'));
    }
}
