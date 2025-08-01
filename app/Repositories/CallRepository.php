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
            $analysis  = $choices[$i]['message']['content'] ?? '';
            $keywords  = null;
            $data = json_decode($analysis, true);
            if (is_array($data) && isset($data['keywords'])) {
                $keywords = is_array($data['keywords']) ? implode(',', $data['keywords']) : (string)$data['keywords'];
            }

            $stmt = $this->db->prepare(
                'UPDATE calls
                     SET analysis        = :analysis,
                         ai_keywords     = :keywords,
                         pending_analysis = 0,
                         ai_processed_at  = :now
                   WHERE id = :id'
            );
            $stmt->execute([
                ':analysis' => $analysis,
                ':keywords' => $keywords,
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

    /** Aggregate global stats since the given date */
    public function summarySince(\DateTimeInterface $since): array
    {
        $sql = 'SELECT
                    COUNT(*)                                   AS total_calls,
                    COUNT(CASE WHEN status = "answered" THEN 1 END) AS answered_calls,
                    COUNT(CASE WHEN status = "missed" THEN 1 END)   AS missed_calls,
                    COUNT(CASE WHEN direction = "inbound" THEN 1 END)  AS inbound_calls,
                    COUNT(CASE WHEN direction = "outbound" THEN 1 END) AS outbound_calls,
                    AVG(CASE WHEN status = "answered" THEN duration END) AS avg_duration
                FROM calls
                WHERE created_at >= :since';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since->format('Y-m-d H:i:s'));
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_calls'    => (int)($row['total_calls'] ?? 0),
            'answered_calls' => (int)($row['answered_calls'] ?? 0),
            'missed_calls'   => (int)($row['missed_calls'] ?? 0),
            'inbound_calls'  => (int)($row['inbound_calls'] ?? 0),
            'outbound_calls' => (int)($row['outbound_calls'] ?? 0),
            'avg_duration'   => (float)($row['avg_duration'] ?? 0),
        ];
    }

    /** Daily call trends since the given date */
    public function trendsSince(\DateTimeInterface $since): array
    {
        $sql = 'SELECT
                    DATE(created_at) AS dt,
                    COUNT(*) AS total_calls,
                    COUNT(CASE WHEN status = "answered" THEN 1 END) AS answered_calls,
                    COUNT(CASE WHEN status = "missed" THEN 1 END) AS missed_calls,
                    AVG(duration) AS avg_duration
                FROM calls
                WHERE created_at >= :since
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since->format('Y-m-d H:i:s'));
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $r) => [
                'date'           => $r['dt'],
                'total_calls'    => (int)$r['total_calls'],
                'answered_calls' => (int)$r['answered_calls'],
                'missed_calls'   => (int)$r['missed_calls'],
                'avg_duration'   => (float)($r['avg_duration'] ?? 0),
            ],
            $rows
        );
    }

    /** Recent calls since the given date */
    public function recentSince(\DateTimeInterface $since, int $limit = 10): array
    {
        $sql = 'SELECT id, phone_number, direction, status, duration, ai_sentiment, created_at
                FROM calls
                WHERE created_at >= :since
                ORDER BY created_at DESC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':since', $since->format('Y-m-d H:i:s'));
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
