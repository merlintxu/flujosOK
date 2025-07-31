<?php
namespace Tests;

use FlujosDimension\Console\TokenCleanupCommand;
use FlujosDimension\Core\Application;
use FlujosDimension\Core\Logger;
use FlujosDimension\Core\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TokenCleanupCommandTest extends TestCase
{
    public function testCommandReportsRemovedCount(): void
    {
        $app = new Application();
        $container = $app->getContainer();
        $container->flush();
        $container->instance('logger', new Logger(sys_get_temp_dir()));

        $jwt = new class {
            public function cleanupExpiredTokens() { return 5; }
        };
        $container->instance(JWT::class, $jwt);
        $container->alias(JWT::class, 'jwtService');

        $command = new TokenCleanupCommand($app);
        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Removed: 5', $tester->getDisplay());
    }
}
