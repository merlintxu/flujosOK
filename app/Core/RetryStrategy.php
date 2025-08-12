<?php
declare(strict_types=1);

namespace FlujosDimension\Core;

use Exception;
use RuntimeException;

/**
 * Retry strategy with exponential backoff and jitter
 */
final class RetryStrategy
{
    private int $maxAttempts;
    private int $baseDelayMs;
    private int $maxDelayMs;
    private float $jitterFactor;
    private array $retryableStatusCodes;
    private array $retryableExceptions;

    public function __construct(
        int $maxAttempts = 3,
        int $baseDelayMs = 1000,
        int $maxDelayMs = 30000,
        float $jitterFactor = 0.1,
        array $retryableStatusCodes = [429, 500, 502, 503, 504],
        array $retryableExceptions = [RuntimeException::class]
    ) {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->baseDelayMs = max(100, $baseDelayMs);
        $this->maxDelayMs = max($baseDelayMs, $maxDelayMs);
        $this->jitterFactor = max(0.0, min(1.0, $jitterFactor));
        $this->retryableStatusCodes = $retryableStatusCodes;
        $this->retryableExceptions = $retryableExceptions;
    }

    /**
     * Execute a callable with retry logic
     *
     * @param callable $operation The operation to execute
     * @param string|null $operationName Name for logging purposes
     * @param string|null $correlationId Correlation ID for tracing
     * @return mixed The result of the operation
     * @throws Exception If all retry attempts fail
     */
    public function execute(callable $operation, ?string $operationName = null, ?string $correlationId = null)
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxAttempts) {
            try {
                $result = $operation();
                
                // Log successful retry if this wasn't the first attempt
                if ($attempt > 1) {
                    $this->logRetrySuccess($operationName, $attempt, $correlationId);
                }
                
                return $result;
                
            } catch (Exception $e) {
                $lastException = $e;
                
                // Check if this exception is retryable
                if (!$this->isRetryable($e)) {
                    $this->logNonRetryableError($operationName, $e, $correlationId);
                    throw $e;
                }
                
                // If this is the last attempt, don't retry
                if ($attempt >= $this->maxAttempts) {
                    $this->logMaxAttemptsReached($operationName, $attempt, $e, $correlationId);
                    break;
                }
                
                // Calculate delay with exponential backoff and jitter
                $delay = $this->calculateDelay($attempt);
                
                $this->logRetryAttempt($operationName, $attempt, $delay, $e, $correlationId);
                
                // Sleep for the calculated delay
                usleep($delay * 1000); // Convert to microseconds
                
                $attempt++;
            }
        }

        // All attempts failed, throw the last exception
        throw $lastException ?? new RuntimeException('All retry attempts failed');
    }

    /**
     * Check if an exception is retryable
     */
    private function isRetryable(Exception $e): bool
    {
        // Check if exception type is retryable
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($e instanceof $retryableClass) {
                return true;
            }
        }

        // Check if it's an HTTP exception with retryable status code
        if (method_exists($e, 'getStatusCode')) {
            $statusCode = $e->getStatusCode();
            return in_array($statusCode, $this->retryableStatusCodes, true);
        }

        // Check if the exception message contains HTTP status codes
        $message = $e->getMessage();
        foreach ($this->retryableStatusCodes as $code) {
            if (strpos($message, (string)$code) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay with exponential backoff and jitter
     */
    private function calculateDelay(int $attempt): int
    {
        // Exponential backoff: baseDelay * 2^(attempt-1)
        $exponentialDelay = $this->baseDelayMs * (2 ** ($attempt - 1));
        
        // Cap at maximum delay
        $delay = min($exponentialDelay, $this->maxDelayMs);
        
        // Add jitter to avoid thundering herd
        $jitter = $delay * $this->jitterFactor * (mt_rand() / mt_getrandmax());
        $delay = (int)($delay + $jitter);
        
        return $delay;
    }

    /**
     * Log retry attempt
     */
    private function logRetryAttempt(
        ?string $operationName,
        int $attempt,
        int $delayMs,
        Exception $e,
        ?string $correlationId
    ): void {
        $context = [
            'operation' => $operationName ?? 'unknown',
            'attempt' => $attempt,
            'max_attempts' => $this->maxAttempts,
            'delay_ms' => $delayMs,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId
        ];

        error_log(sprintf(
            'Retry attempt %d/%d for %s (delay: %dms): %s [correlation_id: %s]',
            $attempt,
            $this->maxAttempts,
            $operationName ?? 'operation',
            $delayMs,
            $e->getMessage(),
            $correlationId ?? 'none'
        ));
    }

    /**
     * Log successful retry
     */
    private function logRetrySuccess(?string $operationName, int $attempt, ?string $correlationId): void
    {
        error_log(sprintf(
            'Retry successful for %s on attempt %d [correlation_id: %s]',
            $operationName ?? 'operation',
            $attempt,
            $correlationId ?? 'none'
        ));
    }

    /**
     * Log non-retryable error
     */
    private function logNonRetryableError(?string $operationName, Exception $e, ?string $correlationId): void
    {
        error_log(sprintf(
            'Non-retryable error for %s: %s [correlation_id: %s]',
            $operationName ?? 'operation',
            $e->getMessage(),
            $correlationId ?? 'none'
        ));
    }

    /**
     * Log max attempts reached
     */
    private function logMaxAttemptsReached(
        ?string $operationName,
        int $attempts,
        Exception $e,
        ?string $correlationId
    ): void {
        error_log(sprintf(
            'Max retry attempts (%d) reached for %s: %s [correlation_id: %s]',
            $attempts,
            $operationName ?? 'operation',
            $e->getMessage(),
            $correlationId ?? 'none'
        ));
    }

    /**
     * Create a retry strategy for API calls
     */
    public static function forApiCalls(): self
    {
        return new self(
            maxAttempts: 3,
            baseDelayMs: 1000,
            maxDelayMs: 10000,
            jitterFactor: 0.1,
            retryableStatusCodes: [429, 500, 502, 503, 504],
            retryableExceptions: [RuntimeException::class]
        );
    }

    /**
     * Create a retry strategy for critical operations
     */
    public static function forCriticalOperations(): self
    {
        return new self(
            maxAttempts: 5,
            baseDelayMs: 2000,
            maxDelayMs: 30000,
            jitterFactor: 0.2,
            retryableStatusCodes: [429, 500, 502, 503, 504],
            retryableExceptions: [RuntimeException::class]
        );
    }

    /**
     * Create a retry strategy for background jobs
     */
    public static function forBackgroundJobs(): self
    {
        return new self(
            maxAttempts: 3,
            baseDelayMs: 5000,
            maxDelayMs: 60000,
            jitterFactor: 0.3,
            retryableStatusCodes: [429, 500, 502, 503, 504],
            retryableExceptions: [RuntimeException::class]
        );
    }
}

