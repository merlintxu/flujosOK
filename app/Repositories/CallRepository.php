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
                  AND has_recording = 1
                  AND (recording_path IS NOT NULL AND recording_path <> \'\')
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

    /** Insert a call using mapped keys. Ignores duplicates by ringover_id.
     *  @return int Number of rows inserted (0 if ignored)
     */
    public function insertOrIgnore(array $call): int
    {
        if (empty($call['ringover_id'])) {
            return 0;
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $prefix = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        $sql = "$prefix INTO calls (
                    ringover_id, call_id, phone_number, contact_number,
                    caller_name, contact_name, direction, status, duration,
                    recording_url, voicemail_url, start_time, total_duration,
                    incall_duration, last_state, is_answered, created_at
                ) VALUES (
                    :ringover_id, :call_id, :phone_number, :contact_number,
                    :caller_name, :contact_name, :direction, :status, :duration,
                    :recording_url, :voicemail_url, :start_time, :total_duration,
                    :incall_duration, :last_state, :is_answered, :created_at
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ringover_id'   => $call['ringover_id']    ?? null,
            ':call_id'       => $call['call_id']        ?? null,
            ':phone_number'  => $call['phone_number']   ?? null,
            ':contact_number'=> $call['contact_number'] ?? null,
            ':caller_name'   => $call['caller_name']    ?? null,
            ':contact_name'  => $call['contact_name']   ?? null,
            ':direction'     => $call['direction']      ?? 'inbound',
            ':status'        => $call['status']         ?? 'pending',
            ':duration'      => $call['duration']       ?? 0,
            ':recording_url' => $call['recording_url']  ?? null,
            ':voicemail_url' => $call['voicemail_url']  ?? null,
            ':start_time'    => $call['start_time']     ?? null,
            ':total_duration'=> $call['total_duration'] ?? null,
            ':incall_duration'=> $call['incall_duration'] ?? null,
            ':last_state'    => $call['last_state']     ?? null,
            ':is_answered'   => isset($call['is_answered']) ? (int)$call['is_answered'] : null,
            ':created_at'    => $call['start_time']     ?? date('Y-m-d H:i:s'),
        ]);

        return (int)$stmt->rowCount();
    }

    /**
     * Retrieve the internal ID for a given Ringover ID.
     */
    public function findIdByRingoverId(string $ringoverId): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM calls WHERE ringover_id = :rid');
        $stmt->execute([':rid' => $ringoverId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /**
     * Mark whether a call is pending AI analysis.
     */
    public function setPendingAnalysis(int $callId, bool $pending): void
    {
        $stmt = $this->db->prepare('UPDATE calls SET pending_analysis = :p WHERE id = :id');
        $stmt->execute([':p' => $pending ? 1 : 0, ':id' => $callId]);
    }

    /**
     * Mark whether a call has pending recordings to download.
     */
    public function setPendingRecordings(int $callId, bool $pending): void
    {
        $stmt = $this->db->prepare('UPDATE calls SET pending_recordings = :p WHERE id = :id');
        $stmt->execute([':p' => $pending ? 1 : 0, ':id' => $callId]);
    }

    /**
     * Persist recording metadata for a call and mark it as having a recording.
     *
     * @param array{url?:string,path?:string,file_path?:string,size?:int,file_size?:int,duration?:int,format?:string} $recordInfo
     */
    public function addRecording(int $callId, array $recordInfo): void
    {
        $path = $recordInfo['path'] ?? $recordInfo['file_path'] ?? '';
        $size = $recordInfo['size'] ?? $recordInfo['file_size'] ?? 0;
        $duration = $recordInfo['duration'] ?? 0;
        $format = $recordInfo['format'] ?? 'mp3';
        $url = $recordInfo['url'] ?? ($recordInfo['recording_url'] ?? null);

        $this->db->beginTransaction();

        $insert = $this->db->prepare(
            'INSERT INTO call_recordings (call_id, file_path, file_size, duration, format) VALUES (:call_id, :file_path, :file_size, :duration, :format)'
        );
        $insert->execute([
            ':call_id' => $callId,
            ':file_path' => $path,
            ':file_size' => $size,
            ':duration' => $duration,
            ':format' => $format,
        ]);

        $update = $this->db->prepare(
            'UPDATE calls SET recording_url = :url, recording_path = :path, has_recording = 1 WHERE id = :id'
        );
        $update->execute([
            ':url' => $url,
            ':path' => $path,
            ':id' => $callId,
        ]);

        $this->db->commit();
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
