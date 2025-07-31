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

    /** Insert a call if ringover_id not present */
    public function insertOrIgnore(array $call): void
    {
        $sql = 'INSERT IGNORE INTO calls (ringover_id, phone_number, direction, status, duration, recording_url, created_at) '
             . 'VALUES (:ringover_id, :phone_number, :direction, :status, :duration, :recording_url, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ringover_id'  => $call['id']          ?? null,
            ':phone_number' => $call['phone_number'] ?? null,
            ':direction'    => $call['direction']    ?? 'inbound',
            ':status'       => $call['status']       ?? 'pending',
            ':duration'     => $call['duration']     ?? 0,
            ':recording_url'=> $call['recording_url']?? null,
            ':created_at'   => $call['start_time']   ?? date('Y-m-d H:i:s'),
        ]);
    }

    /** Return calls not yet synced with CRM */
    public function callsNotInCrm(): array
    {
        $sql = 'SELECT * FROM calls WHERE crm_synced = 0 ORDER BY created_at ASC';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mark a call as synced to CRM */
    public function markCrmSynced(int $id, int $dealId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE calls SET crm_synced = 1, pipedrive_deal_id = :dealId WHERE id = :id'
        );
        $stmt->execute([':dealId' => $dealId, ':id' => $id]);
    }

    /**
     * Get aggregated statistics for a date range grouped by day.
     *
     * @return array<int,array<string,mixed>>
     */
    public function callTrends(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $sql = 'SELECT DATE(created_at) AS date,
                       COUNT(*) AS total_calls,
                       COUNT(CASE WHEN status = "answered" OR is_answered = 1 THEN 1 END) AS answered_calls,
                       COUNT(*) - COUNT(CASE WHEN status = "answered" OR is_answered = 1 THEN 1 END) AS missed_calls,
                       AVG(duration) AS avg_duration
                FROM calls
                WHERE created_at BETWEEN :start AND :end
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quick statistics for a period.
     *
     * @return array<string,mixed>
     */
    public function summary(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $sql = 'SELECT COUNT(*) AS total_calls,
                       COUNT(CASE WHEN status = "answered" OR is_answered = 1 THEN 1 END) AS answered_calls,
                       COUNT(CASE WHEN direction = "inbound" THEN 1 END) AS inbound_calls,
                       COUNT(CASE WHEN direction = "outbound" THEN 1 END) AS outbound_calls,
                       AVG(duration) AS avg_duration
                FROM calls
                WHERE created_at BETWEEN :start AND :end';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'total_calls'    => 0,
            'answered_calls' => 0,
            'inbound_calls'  => 0,
            'outbound_calls' => 0,
            'avg_duration'   => 0,
        ];
    }
}
