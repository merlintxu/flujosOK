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
    public function enqueue(string $taskType, array $data, int $priority = 5): string
    {
        $taskId = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO async_tasks (task_id, task_type, task_data, priority, status, attempts, scheduled_at, created_at) VALUES (:task_id,:task_type,:task_data,:priority,\'pending\',0,:scheduled_at,:created_at)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':task_type' => $taskType,
            ':task_data' => json_encode($data, JSON_THROW_ON_ERROR),
            ':priority' => $priority,
            ':scheduled_at' => $now,
            ':created_at' => $now,
        ]);
        return $taskId;
    }
}
