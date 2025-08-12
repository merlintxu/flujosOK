<?php
declare(strict_types=1);

namespace FlujosDimension\Core;

use PDO;

/**
 * Webhook deduplicator for ensuring idempotency
 */
final class WebhookDeduplicator
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'default_ttl' => 3600, // 1 hour default TTL
            'max_ttl' => 86400,    // 24 hours max TTL
        ], $config);
    }

    /**
     * Check if webhook should be processed (not a duplicate)
     *
     * @param string $webhookType Type of webhook (e.g., 'ringover_call', 'pipedrive_deal')
     * @param array $payload Webhook payload
     * @param string|null $correlationId Correlation ID for tracing
     * @param int|null $ttlSeconds TTL for deduplication record
     * @return array ['should_process' => bool, 'deduplication_key' => string, 'is_duplicate' => bool]
     */
    public function shouldProcess(
        string $webhookType,
        array $payload,
        ?string $correlationId = null,
        ?int $ttlSeconds = null
    ): array {
        $ttl = $ttlSeconds ?? $this->getTtlForWebhookType($webhookType);
        $ttl = min($ttl, $this->config['max_ttl']); // Cap at max TTL
        
        $deduplicationKey = $this->generateDeduplicationKey($webhookType, $payload);
        $payloadHash = $this->generatePayloadHash($payload);
        
        $start = microtime(true);
        
        try {
            // Check if we already have this webhook
            $existing = $this->getExistingRecord($deduplicationKey);
            
            if ($existing) {
                // Webhook already processed
                $this->logWebhookProcessing(
                    $webhookType,
                    $deduplicationKey,
                    $correlationId,
                    'duplicate',
                    count($payload),
                    (int)((microtime(true) - $start) * 1000)
                );
                
                return [
                    'should_process' => false,
                    'deduplication_key' => $deduplicationKey,
                    'is_duplicate' => true,
                    'original_processed_at' => $existing['processed_at']
                ];
            }
            
            // Record this webhook as being processed
            $this->recordWebhook($deduplicationKey, $webhookType, $payloadHash, $correlationId, $ttl);
            
            $this->logWebhookProcessing(
                $webhookType,
                $deduplicationKey,
                $correlationId,
                'processed',
                count($payload),
                (int)((microtime(true) - $start) * 1000)
            );
            
            return [
                'should_process' => true,
                'deduplication_key' => $deduplicationKey,
                'is_duplicate' => false
            ];
            
        } catch (\Exception $e) {
            // If deduplication fails, allow processing to avoid blocking webhooks
            $this->logWebhookProcessing(
                $webhookType,
                $deduplicationKey,
                $correlationId,
                'failed',
                count($payload),
                (int)((microtime(true) - $start) * 1000),
                $e->getMessage()
            );
            
            return [
                'should_process' => true,
                'deduplication_key' => $deduplicationKey,
                'is_duplicate' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mark webhook processing as failed (for cleanup)
     */
    public function markFailed(string $deduplicationKey, string $error): void
    {
        try {
            // Remove from deduplication table so it can be retried
            $stmt = $this->db->prepare('DELETE FROM webhook_deduplication WHERE deduplication_key = :key');
            $stmt->execute([':key' => $deduplicationKey]);
            
            // Log the failure
            $stmt = $this->db->prepare(
                'UPDATE webhook_processing_logs 
                 SET status = "failed", error_message = :error 
                 WHERE deduplication_key = :key 
                 ORDER BY created_at DESC 
                 LIMIT 1'
            );
            $stmt->execute([
                ':key' => $deduplicationKey,
                ':error' => $error
            ]);
        } catch (\Exception $e) {
            // Log but don't throw - this is cleanup
            error_log("Failed to mark webhook as failed: " . $e->getMessage());
        }
    }

    /**
     * Get deduplication statistics
     */
    public function getStats(int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));
        
        $stmt = $this->db->prepare('
            SELECT 
                webhook_type,
                status,
                COUNT(*) as count,
                AVG(processing_time_ms) as avg_processing_time,
                MAX(processing_time_ms) as max_processing_time
            FROM webhook_processing_logs 
            WHERE created_at >= :since
            GROUP BY webhook_type, status
            ORDER BY webhook_type, status
        ');
        $stmt->execute([':since' => $since]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup expired records manually
     */
    public function cleanup(): array
    {
        $start = microtime(true);
        
        // Cleanup expired deduplication records
        $stmt = $this->db->prepare('DELETE FROM webhook_deduplication WHERE expires_at < NOW()');
        $stmt->execute();
        $dedupCleaned = $stmt->rowCount();
        
        // Cleanup old processing logs (keep 30 days)
        $stmt = $this->db->prepare('DELETE FROM webhook_processing_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $stmt->execute();
        $logsCleaned = $stmt->rowCount();
        
        $duration = (int)((microtime(true) - $start) * 1000);
        
        return [
            'deduplication_records_cleaned' => $dedupCleaned,
            'processing_logs_cleaned' => $logsCleaned,
            'duration_ms' => $duration
        ];
    }

    /**
     * Generate deduplication key from webhook type and payload
     */
    private function generateDeduplicationKey(string $webhookType, array $payload): string
    {
        // Extract key fields based on webhook type
        $keyFields = $this->getKeyFieldsForWebhookType($webhookType, $payload);
        
        // Create a stable key from the fields
        $keyData = json_encode($keyFields, JSON_SORT_KEYS);
        $hash = hash('sha256', $keyData);
        
        return "{$webhookType}:{$hash}";
    }

    /**
     * Generate payload hash for integrity checking
     */
    private function generatePayloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_SORT_KEYS));
    }

    /**
     * Get key fields for deduplication based on webhook type
     */
    private function getKeyFieldsForWebhookType(string $webhookType, array $payload): array
    {
        switch ($webhookType) {
            case 'ringover_call':
                return [
                    'call_id' => $payload['call_id'] ?? null,
                    'event_type' => $payload['event_type'] ?? null,
                    'timestamp' => $payload['timestamp'] ?? null,
                ];
                
            case 'pipedrive_deal':
                return [
                    'deal_id' => $payload['current']['id'] ?? null,
                    'event_type' => $payload['event'] ?? null,
                    'timestamp' => $payload['timestamp'] ?? null,
                ];
                
            case 'n8n_workflow':
                return [
                    'workflow_id' => $payload['workflow_id'] ?? null,
                    'execution_id' => $payload['execution_id'] ?? null,
                    'timestamp' => $payload['timestamp'] ?? null,
                ];
                
            default:
                // Generic deduplication based on entire payload
                return $payload;
        }
    }

    /**
     * Get TTL for specific webhook type
     */
    private function getTtlForWebhookType(string $webhookType): int
    {
        $ttls = [
            'ringover_call' => 3600,    // 1 hour
            'pipedrive_deal' => 1800,   // 30 minutes
            'n8n_workflow' => 7200,     // 2 hours
        ];
        
        return $ttls[$webhookType] ?? $this->config['default_ttl'];
    }

    /**
     * Check if deduplication record exists
     */
    private function getExistingRecord(string $deduplicationKey): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM webhook_deduplication 
            WHERE deduplication_key = :key AND expires_at > NOW()
        ');
        $stmt->execute([':key' => $deduplicationKey]);
        
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    }

    /**
     * Record webhook in deduplication table
     */
    private function recordWebhook(
        string $deduplicationKey,
        string $webhookType,
        string $payloadHash,
        ?string $correlationId,
        int $ttl
    ): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        
        $stmt = $this->db->prepare('
            INSERT INTO webhook_deduplication 
            (deduplication_key, webhook_type, payload_hash, correlation_id, expires_at)
            VALUES (:key, :type, :hash, :correlation_id, :expires_at)
        ');
        
        $stmt->execute([
            ':key' => $deduplicationKey,
            ':type' => $webhookType,
            ':hash' => $payloadHash,
            ':correlation_id' => $correlationId,
            ':expires_at' => $expiresAt
        ]);
    }

    /**
     * Log webhook processing activity
     */
    private function logWebhookProcessing(
        string $webhookType,
        string $deduplicationKey,
        ?string $correlationId,
        string $status,
        int $payloadSize,
        int $processingTimeMs,
        ?string $errorMessage = null
    ): void {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO webhook_processing_logs 
                (webhook_type, deduplication_key, correlation_id, status, payload_size, processing_time_ms, error_message)
                VALUES (:type, :key, :correlation_id, :status, :size, :time, :error)
            ');
            
            $stmt->execute([
                ':type' => $webhookType,
                ':key' => $deduplicationKey,
                ':correlation_id' => $correlationId,
                ':status' => $status,
                ':size' => $payloadSize,
                ':time' => $processingTimeMs,
                ':error' => $errorMessage
            ]);
        } catch (\Exception $e) {
            // Don't throw on logging failures
            error_log("Failed to log webhook processing: " . $e->getMessage());
        }
    }
}

