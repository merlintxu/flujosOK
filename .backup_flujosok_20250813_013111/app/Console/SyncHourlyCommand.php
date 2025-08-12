<?php
namespace FlujosDimension\Console;

use FlujosDimension\Core\Application;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Services\AnalyticsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to run hourly synchronization with Ringover.
 */
class SyncHourlyCommand extends Command
{
    protected static $defaultName = 'sync:hourly';
    private Application $app;

    public function __construct(?Application $app = null)
    {
        parent::__construct();
        $this->app = $app ?? new Application();
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronize calls from Ringover (last hour).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->app->getContainer();

        try {
            if (!$container->bound(RingoverService::class) || !$container->bound('callRepository')) {
                $output->writeln('<info>No services configured</info>');
                return Command::SUCCESS;
            }

            /** @var RingoverService $ringover */
            $ringover = $container->resolve(RingoverService::class);
            /** @var CallRepository $repo */
            $repo     = $container->resolve('callRepository');
            /** @var AnalyticsService|null $analytics */
            $analytics = $container->bound('analyticsService') ? $container->resolve('analyticsService') : null;

            $since = new \DateTimeImmutable('-1 hour');
            $inserted = 0;
            $batchId = bin2hex(random_bytes(16));
            foreach ($ringover->getCalls($since, false, null, $batchId) as $call) {
                $correlationId = bin2hex(random_bytes(16));
                $mapped = $ringover->mapCallFields($call) + [
                    'batch_id'       => $batchId,
                    'correlation_id' => $correlationId,
                ];
                $repo->insertOrIgnore($mapped);
                $container->resolve('logger')->info('call_synced', [
                    'batch_id' => $batchId,
                    'correlation_id' => $correlationId,
                    'call_id' => $mapped['call_id'] ?? null,
                ]);
                $inserted++;
            }

            if ($analytics) {
                $analytics->processBatch();
            }

            $container->resolve('logger')->info('sync_hourly', ['inserted' => $inserted]);
            $output->writeln("<info>Inserted: {$inserted}</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $container->resolve('logger')->error('Hourly sync failed', ['exception' => $e]);
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
