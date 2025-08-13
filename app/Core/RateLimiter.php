<?php
declare(strict_types=1);

namespace FlujosDimension\Core;

use PDO;
use PDOException;

/**
 * Rate limiter using token bucket algorithm
 */
final class RateLimiter
{
    private PDO $db;
    private array $config;
    private bool $passthrough = false;
    private ?Logger $logger;
    /** @var array<string,array|null> */
    private array $serviceConfigCache = [];

    public function __construct(PDO $db, array $config = [], ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = array_merge([
            'default_capacity' => 100,
            'default_refill_rate' => 10, // tokens per second
            'cleanup_interval' => 3600, // 1 hour
        ], $config);

        $this->checkTables();
    }

    private function checkTables(): void
    {
        $required = ['rate_limit_buckets', 'rate_limit_logs'];
        $missing = [];

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($required as $table) {
            try {
                if ($driver === 'sqlite') {
                    $stmt = $this->db->prepare('SELECT name FROM sqlite_master WHERE type = "table" AND name = :table');
                } else {
                    $stmt = $this->db->prepare(
                        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
                    );
                }
                $stmt->execute([':table' => $table]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $table;
                }
            } catch (PDOException $e) {
                $missing = $required;
                break;
            }
        }

        if ($missing) {
            $this->passthrough = true;
            $this->logger?->warning('RateLimiter: passthrough=true', ['missing_tables' => $missing]);
        } else {
            $this->logger?->info('RateLimiter: passthrough=false');
        }
    }

    /**
     * Get rate limit configuration for a service from database (cached)
     */
    private function getServiceConfig(string $service): ?array
    {
        if (array_key_exists($service, $this->serviceConfigCache)) {
            return $this->serviceConfigCache[$service];
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT max_requests_per_minute, max_requests_per_hour FROM rate_limit_config WHERE service_name = :service'
            );
            $stmt->execute([':service' => $service]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $this->serviceConfigCache[$service] = $row;
            return $row;
        } catch (PDOException $e) {
            $this->logger?->error('rate_limit_config_error', ['service' => $service, 'error' => $e->getMessage()]);
            $this->serviceConfigCache[$service] = null;
            return null;
        }
    }

    /**
     * Check if operation is allowed and consume token if available
     *
     * @param string $key Unique identifier for the rate limit (e.g., "openai:transcribe", "pipedrive:api")
     * @param int $tokens Number of tokens to consume (default: 1)
     * @param array $limits Override limits for this specific check
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public function isAllowed(string $key, int $tokens = 1, array $limits = []): array
    {
        if ($this->passthrough) {
            $capacity = $limits['capacity'] ?? $this->config['default_capacity'];
            return [
                'allowed' => true,
                'remaining' => $capacity,
                'reset_time' => time(),
                'capacity' => $capacity,
                'refill_rate' => $limits['refill_rate'] ?? $this->config['default_refill_rate']
            ];
        }

        $capacity = $limits['capacity'] ?? $this->getCapacityForKey($key);
        $refillRate = $limits['refill_rate'] ?? $this->getRefillRateForKey($key);
        
        $now = time();
        
        // Get or create bucket
        $bucket = $this->getBucket($key, $capacity, $now);
        
        // Calculate tokens to add based on time elapsed
        $timeDiff = $now - $bucket['last_refill'];
        $tokensToAdd = min($capacity, $bucket['tokens'] + ($timeDiff * $refillRate));
        
        // Check if we have enough tokens
        $allowed = $tokensToAdd >= $tokens;
        
        if ($allowed) {
            // Consume tokens
            $newTokens = $tokensToAdd - $tokens;
            $this->updateBucket($key, $newTokens, $now);
        } else {
            // Update last refill time even if not allowed
            $this->updateBucket($key, $tokensToAdd, $now);
        }
        
        // Calculate reset time (when bucket will be full again)
        $resetTime = $now + (int)ceil(($capacity - $tokensToAdd) / $refillRate);
        
        return [
            'allowed' => $allowed,
            'remaining' => (int)$tokensToAdd,
            'reset_time' => $resetTime,
            'capacity' => $capacity,
            'refill_rate' => $refillRate
        ];
    }

    /**
     * Get current status of a rate limit bucket
     */
    public function getStatus(string $key): array
    {
        if ($this->passthrough) {
            $capacity = $this->config['default_capacity'];
            $refillRate = $this->config['default_refill_rate'];
            $now = time();
            return [
                'key' => $key,
                'tokens' => $capacity,
                'capacity' => $capacity,
                'refill_rate' => $refillRate,
                'last_refill' => $now,
                'reset_time' => $now
            ];
        }

        $capacity = $this->getCapacityForKey($key);
        $refillRate = $this->getRefillRateForKey($key);
        $now = time();

        $bucket = $this->getBucket($key, $capacity, $now);

        // Calculate current tokens
        $timeDiff = $now - $bucket['last_refill'];
        $currentTokens = min($capacity, $bucket['tokens'] + ($timeDiff * $refillRate));

        return [
            'key' => $key,
            'tokens' => (int)$currentTokens,
            'capacity' => $capacity,
            'refill_rate' => $refillRate,
            'last_refill' => $bucket['last_refill'],
            'reset_time' => $now + (int)ceil(($capacity - $currentTokens) / $refillRate)
        ];
    }

    /**
     * Reset a rate limit bucket (admin function)
     */
    public function reset(string $key): void
    {
        if ($this->passthrough) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM rate_limit_buckets WHERE bucket_key = :key');
        $stmt->execute([':key' => $key]);
    }

    /**
     * Get all active buckets (for monitoring)
     */
    public function getAllBuckets(): array
    {
        if ($this->passthrough) {
            return [];
        }

        $stmt = $this->db->query('SELECT * FROM rate_limit_buckets ORDER BY last_refill DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup old buckets
     */
    public function cleanup(): int
    {
        if ($this->passthrough) {
            return 0;
        }

        $cutoff = time() - $this->config['cleanup_interval'];
        $stmt = $this->db->prepare('DELETE FROM rate_limit_buckets WHERE last_refill < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    /**
     * Get or create bucket from database
     */
    private function getBucket(string $key, int $capacity, int $now): array
    {
        $stmt = $this->db->prepare('SELECT * FROM rate_limit_buckets WHERE bucket_key = :key');
        $stmt->execute([':key' => $key]);
        $bucket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bucket) {
            // Create new bucket
            $stmt = $this->db->prepare(
                'INSERT INTO rate_limit_buckets (bucket_key, tokens, capacity, last_refill, created_at) 
                 VALUES (:key, :tokens, :capacity, :now, :now)'
            );
            $stmt->execute([
                ':key' => $key,
                ':tokens' => $capacity,
                ':capacity' => $capacity,
                ':now' => $now
            ]);
            
            return [
                'bucket_key' => $key,
                'tokens' => $capacity,
                'capacity' => $capacity,
                'last_refill' => $now
            ];
        }
        
        return $bucket;
    }

    /**
     * Update bucket in database
     */
    private function updateBucket(string $key, float $tokens, int $now): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rate_limit_buckets 
             SET tokens = :tokens, last_refill = :now 
             WHERE bucket_key = :key'
        );
        $stmt->execute([
            ':tokens' => $tokens,
            ':now' => $now,
            ':key' => $key
        ]);
    }

    /**
     * Get capacity for a specific key
     */
    private function getCapacityForKey(string $key): int
    {
        $service = explode(':', $key)[0];
        if ($config = $this->getServiceConfig($service)) {
            return (int)$config['max_requests_per_minute'];
        }

        return $this->config['default_capacity'];
    }

    /**
     * Get refill rate for a specific key (tokens per second)
     */
    private function getRefillRateForKey(string $key): float
    {
        $service = explode(':', $key)[0];
        if ($config = $this->getServiceConfig($service)) {
            return ((int)$config['max_requests_per_minute']) / 60.0;
        }

        return $this->config['default_refill_rate'] / 60.0; // Convert per minute to per second
    }

    /**
     * Create rate limit headers for HTTP responses
     */
    public function getHeaders(string $key): array
    {
        $status = $this->getStatus($key);
        
        return [
            'X-RateLimit-Limit' => (string)$status['capacity'],
            'X-RateLimit-Remaining' => (string)$status['tokens'],
            'X-RateLimit-Reset' => (string)$status['reset_time'],
            'X-RateLimit-Key' => $key
        ];
    }

    /**
     * Check multiple keys at once (for complex operations)
     */
    public function checkMultiple(array $checks): array
    {
        $results = [];
        $allAllowed = true;
        
        foreach ($checks as $key => $tokens) {
            $result = $this->isAllowed($key, $tokens);
            $results[$key] = $result;
            
            if (!$result['allowed']) {
                $allAllowed = false;
            }
        }
        
        return [
            'allowed' => $allAllowed,
            'results' => $results
        ];
    }
}

