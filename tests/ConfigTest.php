<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\Core\Config;

class ConfigTest extends TestCase
{
    public function testSetAndGet()
    {
        $config = Config::getInstance();
        $config->set('TEST_KEY', 'test_value');
        $this->assertSame('test_value', $config->get('TEST_KEY'));
    }
}
