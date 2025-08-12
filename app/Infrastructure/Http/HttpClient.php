<?php
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use PDO;
use FlujosDimension\Core\Logger;
use FlujosDimension\Core\RetryStrategy;
use FlujosDimension\Core\RateLimiter;

/**
 * Cliente HTTP genérico con retry strategy mejorado y soporte Retry-After.
 */
final class HttpClient
{
    private Client $client;
    private RetryStrategy $retryStrategy;
    private ?RateLimiter $rateLimiter;

    public function __construct(
        array $config = [],
        private readonly int $maxRetries = 5,
        private readonly int $baseDelayMs = 500,
        private ?PDO $db = null,
        private ?Logger $logger = null,
        ?RetryStrategy $retryStrategy = null,
        ?RateLimiter $rateLimiter = null
    ) {
        // http_errors = false ⇒ devolvemos la respuesta aunque sea 4xx/5xx
        $defaultHeaders = ['User-Agent' => 'FlujosDimensionBot/1.0'];
        $this->client = new Client(array_merge_recursive($config, [
            'headers' => $defaultHeaders,
            'http_errors' => false,
            'timeout' => 15
        ]));

        // Use provided retry strategy or create default one
        $this->retryStrategy = $retryStrategy ?? RetryStrategy::forApiCalls();
        
        // Use provided rate limiter or create default one if DB is available
        $this->rateLimiter = $rateLimiter ?? ($db ? new RateLimiter($db) : null);
    }

    /**
     * @throws RequestException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $service       = $options['service']       ?? 'unknown';
        $correlationId = $options['correlation_id'] ?? null;
        $batchId       = $options['batch_id']       ?? null;
        unset($options['service'], $options['correlation_id'], $options['batch_id']);

        $requestPath = parse_url($uri, PHP_URL_PATH) ?: $uri;

        if ($correlationId !== null) {
            $options['headers']['X-Correlation-ID'] = $correlationId;
        }

        // Apply rate limiting if available
        $rateLimitKey = $this->generateRateLimitKey($service, $method, $requestPath);
        if ($this->rateLimiter) {
            $rateLimitResult = $this->rateLimiter->isAllowed($rateLimitKey);
            
            if (!$rateLimitResult['allowed']) {
                // Rate limit exceeded, throw exception
                $resetTime = $rateLimitResult['reset_time'];
                $waitTime = $resetTime - time();
                
                throw new \RuntimeException(
                    "Rate limit exceeded for {$rateLimitKey}. Reset in {$waitTime} seconds.",
                    429
                );
            }
        }

        $start = microtime(true);

        // Use retry strategy to execute the request
        $response = $this->retryStrategy->execute(
            function() use ($method, $uri, $options) {
                $response = $this->client->request($method, $uri, $options);
                $status = $response->getStatusCode();

                // Throw exception for retryable status codes to trigger retry
                if (in_array($status, [429, 500, 502, 503, 504], true)) {
                    // Check for Retry-After header
                    $retryAfter = $response->getHeaderLine('Retry-After');
                    if ($retryAfter) {
                        // If Retry-After is present, sleep for that duration
                        $delaySeconds = is_numeric($retryAfter) ? (int)$retryAfter : 60;
                        sleep(min($delaySeconds, 300)); // Cap at 5 minutes
                    }
                    
                    throw new \RuntimeException("HTTP {$status} error", $status);
                }

                return $response;
            },
            "{$service}:{$method}:{$requestPath}",
            $correlationId
        );

        // Log and monitor the request
        $elapsedMs = (int)((microtime(true) - $start) * 1000);
        $status = $response->getStatusCode();
        $success = $status >= 200 && $status < 300;
        $body = $success ? null : (string)$response->getBody();

        // Add rate limit headers to response
        if ($this->rateLimiter) {
            $rateLimitHeaders = $this->rateLimiter->getHeaders($rateLimitKey);
            foreach ($rateLimitHeaders as $headerName => $headerValue) {
                $response = $response->withHeader($headerName, $headerValue);
            }
        }

        if ($this->logger) {
            $this->logger->info('api_request', [
                'service'        => $service,
                'request_path'   => $requestPath,
                'method'         => $method,
                'status_code'    => $status,
                'response_time'  => $elapsedMs,
                'success'        => $success,
                'batch_id'       => $batchId,
                'correlation_id' => $correlationId,
                'rate_limit_key' => $rateLimitKey,
            ]);
        }

        if ($this->db) {
            $payload = json_encode([
                'batch_id'       => $batchId,
                'correlation_id' => $correlationId,
                'error'          => $body,
                'rate_limit_key' => $rateLimitKey,
            ], JSON_UNESCAPED_UNICODE);

            $stmt = $this->db->prepare(
                'INSERT INTO api_monitoring (service, request_path, method, response_time, status_code, success, correlation_id, error_message, timestamp)'
                . ' VALUES (:service, :request_path, :method, :response_time, :status_code, :success, :correlation_id, :error_message, NOW())'
            );
            $stmt->execute([
                ':service'       => $service,
                ':request_path'  => $requestPath,
                ':method'        => $method,
                ':response_time' => $elapsedMs,
                ':status_code'   => $status,
                ':success'       => $success ? 1 : 0,
                ':correlation_id'=> $correlationId,
                ':error_message' => $payload,
            ]);
        }

        return $response;
    }

    /**
     * Set a custom retry strategy
     */
    public function setRetryStrategy(RetryStrategy $retryStrategy): void
    {
        $this->retryStrategy = $retryStrategy;
    }

    /**
     * Get current retry strategy
     */
    public function getRetryStrategy(): RetryStrategy
    {
        return $this->retryStrategy;
    }

    /**
     * Set a custom rate limiter
     */
    public function setRateLimiter(?RateLimiter $rateLimiter): void
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Get current rate limiter
     */
    public function getRateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Generate rate limit key for a request
     */
    private function generateRateLimitKey(string $service, string $method, string $path): string
    {
        // Normalize service name
        $service = strtolower($service);
        
        // Create specific keys for different operations
        $operationMap = [
            'openai' => [
                '/audio/transcriptions' => 'transcribe',
                '/chat/completions' => 'chat',
            ],
            'pipedrive' => [
                '/persons' => 'persons',
                '/deals' => 'deals',
                '/activities' => 'activities',
            ],
            'ringover' => [
                '/calls' => 'calls',
                '/recordings' => 'download',
            ]
        ];
        
        $operation = 'api'; // default
        
        if (isset($operationMap[$service])) {
            foreach ($operationMap[$service] as $pathPattern => $op) {
                if (str_contains($path, $pathPattern)) {
                    $operation = $op;
                    break;
                }
            }
        }
        
        return "{$service}:{$operation}";
    }

    /**
     * Check rate limit status for a service
     */
    public function getRateLimitStatus(string $service, string $operation = 'api'): ?array
    {
        if (!$this->rateLimiter) {
            return null;
        }
        
        $key = "{$service}:{$operation}";
        return $this->rateLimiter->getStatus($key);
    }

    /**
     * Reset rate limit for a service (admin function)
     */
    public function resetRateLimit(string $service, string $operation = 'api'): void
    {
        if ($this->rateLimiter) {
            $key = "{$service}:{$operation}";
            $this->rateLimiter->reset($key);
        }
    }

    /**
     * Convenience method for GET requests
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * Convenience method for POST requests
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * Convenience method for PUT requests
     */
    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * Convenience method for DELETE requests
     */
    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * Convenience method for PATCH requests
     */
    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * Get response body as string
     */
    public function getBodyAsString(ResponseInterface $response): string
    {
        return (string)$response->getBody();
    }

    /**
     * Get response body as JSON array
     */
    public function getBodyAsJson(ResponseInterface $response): array
    {
        $body = $this->getBodyAsString($response);
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded ?? [];
    }
}

