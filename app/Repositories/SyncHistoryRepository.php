<?php
namespace FlujosDimension\Repositories;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;

class SyncHistoryRepository
{
    public function __construct(private PDO $db) {}

    public function getLastSyncedAt(): ?DateTimeImmutable
    {
        $stmt = $this->db->query('SELECT last_synced_at FROM sync_history WHERE id = 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!empty($row['last_synced_at'])) {
            return new DateTimeImmutable($row['last_synced_at']);
        }
        return null;
    }

    public function updateLastSyncedAt(DateTimeInterface $time): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE sync_history SET last_synced_at = :ts WHERE id = 1');
            $stmt->execute([':ts' => $time->format('Y-m-d H:i:s')]);
            if ($stmt->rowCount() === 0) {
                $stmt = $this->db->prepare('INSERT INTO sync_history (id, last_synced_at) VALUES (1, :ts)');
                $stmt->execute([':ts' => $time->format('Y-m-d H:i:s')]);
            }
        } catch (PDOException $e) {
            // swallow for now
        }
    }
}
