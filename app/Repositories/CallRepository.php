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
        $sql = 'INSERT INTO calls (
            ringover_id, call_id, contact_number, contact_firstname, contact_lastname, contact_fullname,
            recording_url, voicemail_url, direction, start_time, duration, last_state, summary, raw_json
        ) VALUES (
            :ringover_id, :call_id, :contact_number, :contact_firstname, :contact_lastname, :contact_fullname,
            :recording_url, :voicemail_url, :direction, :start_time, :duration, :last_state, :summary, :raw_json
        )
        ON DUPLICATE KEY UPDATE
            recording_url = VALUES(recording_url),
            voicemail_url = VALUES(voicemail_url),
            summary = VALUES(summary),
            raw_json = VALUES(raw_json)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ringover_id'      => $call['cdr_id'] ?? null,
            ':call_id'          => $call['call_id'] ?? null,
            ':contact_number'   => $call['contact_number'] ?? null,
            ':contact_firstname'=> $call['contact']['firstname'] ?? null,
            ':contact_lastname' => $call['contact']['lastname'] ?? null,
            ':contact_fullname' => $call['contact']['concat_name'] ?? null,
            ':recording_url'    => $call['record'] ?? null,
            ':voicemail_url'    => $call['voicemail'] ?? null,
            ':direction'        => ($call['direction'] ?? 'in') === 'in' ? 'inbound' : 'outbound',
            ':start_time'       => $call['start_time'] ?? date('Y-m-d H:i:s'),
            ':duration'         => $call['total_duration'] ?? 0,
            ':last_state'       => $call['last_state'] ?? null,
            ':summary'          => $call['note'] ?? null,
            ':raw_json'         => json_encode($call, JSON_UNESCAPED_UNICODE),
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
}
