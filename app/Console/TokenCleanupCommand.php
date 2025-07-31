<?php
namespace FlujosDimension\Console;

use FlujosDimension\Core\Application;
use FlujosDimension\Core\JWT;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to remove expired JWT tokens from storage.
 */
class TokenCleanupCommand extends Command
{
    protected static $defaultName = 'token:cleanup';
    private Application $app;

    public function __construct(?Application $app = null)
    {
        parent::__construct();
        $this->app = $app ?? new Application();
    }

    protected function configure(): void
    {
        $this->setDescription('Delete expired or inactive API tokens.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->app->getContainer();

        try {
            if (!$container->bound(JWT::class) && !$container->bound('jwtService')) {
                $output->writeln('<info>No JWT service configured</info>');
                return Command::SUCCESS;
            }

            /** @var JWT $jwt */
            $jwt = $container->resolve(JWT::class);
            $removed = $jwt->cleanupExpiredTokens();
            $output->writeln("<info>Removed: {$removed}</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $container->resolve('logger')->error('token_cleanup_failed', ['exception' => $e]);
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
