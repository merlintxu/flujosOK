<?php
namespace FlujosDimension\Repositories;

use PDO;

class AsyncTaskRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Enqueue a new async task.
     * @param array<string,mixed> $data
     */
    public function enqueue(string $taskType, array $data, int $priority = 5, ?string $correlationId = null, int $maxAttempts = 3, int $retryBackoffSec = 60): string
    {
        $taskId = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        
        // Add correlation ID to task data if provided
        if ($correlationId) {
            $data['correlation_id'] = $correlationId;
        }
        
        $stmt = $this->db->prepare('INSERT INTO async_tasks (task_id, task_type, task_data, priority, status, attempts, visible_at, reserved_at, error_reason, dlq, max_attempts, retry_backoff_sec, created_at, correlation_id) VALUES (:task_id,:task_type,:task_data,:priority,\'pending\',0,:visible_at,NULL,NULL,0,:max_attempts,:retry_backoff_sec,:created_at,:correlation_id)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':task_type' => $taskType,
            ':task_data' => json_encode($data, JSON_THROW_ON_ERROR),
            ':priority' => $priority,
            ':visible_at' => $now,
            ':max_attempts' => $maxAttempts,
            ':retry_backoff_sec' => $retryBackoffSec,
            ':created_at' => $now,
            ':correlation_id' => $correlationId,
        ]);
        return $taskId;
    }
}
