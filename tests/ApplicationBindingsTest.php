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
        try {
            $this->assertInstanceOf(JWT::class, $app->service(JWT::class));
            $cacheDir = sys_get_temp_dir() . '/fd-cache';
            $appCache = new CacheManager($cacheDir);
            $this->assertInstanceOf(CacheManager::class, $appCache);
            rmdir($cacheDir);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
