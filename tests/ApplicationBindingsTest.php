<?php
namespace Tests;

use FlujosDimension\Core\Application;
use FlujosDimension\Core\JWT;
use FlujosDimension\Core\CacheManager;
use PHPUnit\Framework\TestCase;

class ApplicationBindingsTest extends TestCase
{
    public function testJwtAndCacheBindings()
    {
        $app = new Application();
        $app->getContainer()->singleton(\PDO::class, fn () => new \PDO('sqlite::memory:'));
        $app->getContainer()->alias(\PDO::class, 'database');
        try {
            $this->assertInstanceOf(JWT::class, $app->service(JWT::class));
            $this->assertInstanceOf(CacheManager::class, $app->service('cache'));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testCacheServiceIsManager()
    {
        $app = new Application();
        $app->getContainer()->singleton(\PDO::class, fn () => new \PDO('sqlite::memory:'));
        $app->getContainer()->alias(\PDO::class, 'database');
        try {
            $this->assertInstanceOf(CacheManager::class, $app->service('cache'));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
