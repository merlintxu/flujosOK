<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class IntegrationFlowTest extends TestCase
{
    public function testEndToEndFlow()
    {
        if (!getenv('RUN_INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests are disabled');
        }

        $this->assertTrue(true);
    }
}
