<?php
namespace FlujosDimension\Console;

use FlujosDimension\Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

class QueueWorkCommand extends Command
{
    protected static $defaultName = 'queue:work';

    private Application $app;

    public function __construct(?Application $app = null)
    {
        parent::__construct();
        $this->app = $app ?? new Application();
    }

    protected function configure(): void
    {
        $this->setDescription('Process async tasks queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->app->getContainer();
        /** @var PDO $pdo */
        $pdo = $container->resolve(PDO::class);
        /** @var LoggerInterface $logger */
        $logger = $container->resolve('logger');

        while (true) {
            $task = $this->fetchTask($pdo);
            if (!$task) {
                sleep(5);
                continue;
            }

            $this->reserveTask($pdo, (int)$task['id']);

            try {
                $job = $container->make($task['task_type']);
                if (!method_exists($job, 'handle')) {
                    throw new \RuntimeException('Job missing handle method');
                }
                $payload = json_decode($task['task_data'], true) ?? [];
                $job->handle($payload);
                $this->deleteTask($pdo, (int)$task['id']);
                $logger->info('queue_job_success', ['task_id' => $task['task_id']]);
            } catch (Throwable $e) {
                $this->handleFailure($pdo, $task, $e, $logger);
            }
        }

        return Command::SUCCESS;
    }

    private function fetchTask(PDO $pdo): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM async_tasks WHERE dlq = 0 AND visible_at <= NOW() AND (reserved_at IS NULL OR reserved_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)) ORDER BY priority ASC, id ASC LIMIT 1");
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    private function reserveTask(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare("UPDATE async_tasks SET reserved_at = NOW(), attempts = attempts + 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    private function deleteTask(PDO $pdo, int $id): void
    {
        $pdo->prepare('DELETE FROM async_tasks WHERE id = :id')->execute([':id' => $id]);
    }

    private function handleFailure(PDO $pdo, array $task, Throwable $e, LoggerInterface $logger): void
    {
        $attempts = (int)$task['attempts'] + 1;
        $maxAttempts = (int)$task['max_attempts'];
        if ($attempts >= $maxAttempts) {
            $pdo->prepare('UPDATE async_tasks SET dlq = 1, error_reason = :err, reserved_at = NULL WHERE id = :id')
                ->execute([':err' => $e->getMessage(), ':id' => $task['id']]);
            $logger->error('queue_job_failed_dlq', ['task_id' => $task['task_id'], 'error' => $e->getMessage()]);
            return;
        }

        $delay = $attempts * (int)$task['retry_backoff_sec'];
        $pdo->prepare('UPDATE async_tasks SET reserved_at = NULL, visible_at = DATE_ADD(NOW(), INTERVAL :delay SECOND) WHERE id = :id')
            ->execute([':delay' => $delay, ':id' => $task['id']]);
        $logger->warning('queue_job_retry', ['task_id' => $task['task_id'], 'delay' => $delay, 'error' => $e->getMessage()]);
    }
}
