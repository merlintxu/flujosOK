<?php
declare(strict_types=1);

namespace FlujosDimension\Core;

use PDO;

/**
 * Rate limiter using token bucket algorithm
 */
final class RateLimiter
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'default_capacity' => 100,
            'default_refill_rate' => 10, // tokens per second
            'cleanup_interval' => 3600, // 1 hour
        ], $config);
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
        $stmt = $this->db->prepare('DELETE FROM rate_limit_buckets WHERE bucket_key = :key');
        $stmt->execute([':key' => $key]);
    }

    /**
     * Get all active buckets (for monitoring)
     */
    public function getAllBuckets(): array
    {
        $stmt = $this->db->query('SELECT * FROM rate_limit_buckets ORDER BY last_refill DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup old buckets
     */
    public function cleanup(): int
    {
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
        // Service-specific limits
        $limits = [
            'openai:transcribe' => 50,      // 50 requests
            'openai:chat' => 100,           // 100 requests  
            'pipedrive:api' => 200,         // 200 requests
            'ringover:api' => 300,          // 300 requests
            'ringover:download' => 20,      // 20 downloads
        ];
        
        // Check for exact match first
        if (isset($limits[$key])) {
            return $limits[$key];
        }
        
        // Check for service prefix match
        foreach ($limits as $pattern => $limit) {
            if (str_starts_with($key, explode(':', $pattern)[0] . ':')) {
                return $limit;
            }
        }
        
        return $this->config['default_capacity'];
    }

    /**
     * Get refill rate for a specific key (tokens per second)
     */
    private function getRefillRateForKey(string $key): float
    {
        // Service-specific refill rates (tokens per second)
        $rates = [
            'openai:transcribe' => 0.5,     // 30 per minute
            'openai:chat' => 1.0,           // 60 per minute
            'pipedrive:api' => 2.0,         // 120 per minute
            'ringover:api' => 3.0,          // 180 per minute
            'ringover:download' => 0.2,     // 12 per minute
        ];
        
        // Check for exact match first
        if (isset($rates[$key])) {
            return $rates[$key];
        }
        
        // Check for service prefix match
        foreach ($rates as $pattern => $rate) {
            if (str_starts_with($key, explode(':', $pattern)[0] . ':')) {
                return $rate;
            }
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

