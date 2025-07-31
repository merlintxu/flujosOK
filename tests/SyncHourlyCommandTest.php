<?php
namespace Tests;

use FlujosDimension\Console\SyncHourlyCommand;
use FlujosDimension\Core\Application;
use FlujosDimension\Core\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SyncHourlyCommandTest extends TestCase
{
    public function testCommandRunsWithoutServices(): void
    {
        $app = new Application();
        $container = $app->getContainer();
        $container->flush();
        $container->instance('logger', new Logger(sys_get_temp_dir()));
        $container->instance('config', []);

        $command = new SyncHourlyCommand($app);
        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No services configured', $tester->getDisplay());
    }
}
