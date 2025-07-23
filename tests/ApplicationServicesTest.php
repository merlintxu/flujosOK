<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Application;
use FlujosDimension\Core\JWT;
use FlujosDimension\Core\CacheManager;

class ApplicationServicesTest extends TestCase
{
    public function testJwtAndCacheBindings()
    {
        $app = new Application();
        $jwt = $app->service('jwtService');
        $cache = $app->service('cache');

        $this->assertInstanceOf(JWT::class, $jwt);
        $this->assertInstanceOf(CacheManager::class, $cache);

        $cache->set('foo', 'bar', 1);
        $this->assertSame('bar', $cache->get('foo'));

        restore_error_handler();
        restore_exception_handler();
    }
}
