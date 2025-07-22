<?php
declare(strict_types=1);

namespace FlujosDimension\Repositories;

use PDO;

final class CallRepository
{
    public function __construct(private PDO $db) {}

    /** @return array<int,array<string,mixed>> */
    public function pending(int $max = 50): array
    {
        $sql = 'SELECT * FROM calls
                WHERE pending_analysis = 1
                ORDER BY created_at ASC
                LIMIT :max';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':max', $max, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int,array<string,mixed>> $calls
     * @param array<int,array<string,mixed>> $choices
     */
    public function saveBatch(array $calls, array $choices): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->beginTransaction();
        foreach ($calls as $i => $call) {
            $analysis = $choices[$i]['message']['content'] ?? '';

            $stmt = $this->db->prepare(
                'UPDATE calls
                     SET analysis        = :analysis,
                         pending_analysis = 0,
                         ai_processed_at  = :now
                   WHERE id = :id'
            );
            $stmt->execute([
                ':analysis' => $analysis,
                ':now'      => $now,
                ':id'       => $call['id'],
            ]);
        }
        $this->db->commit();
    }
}
