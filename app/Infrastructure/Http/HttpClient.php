<?php
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use PDO;
use FlujosDimension\Core\Logger;

/**
 * Cliente HTTP genérico con back-off exponencial y soporte Retry-After.
 */
final class HttpClient
{
    private Client $client;

    public function __construct(
        array $config = [],
        private readonly int $maxRetries = 5,
        private readonly int $baseDelayMs = 500,
        private ?PDO $db = null,
        private ?Logger $logger = null
    ) {
        // http_errors = false ⇒ devolvemos la respuesta aunque sea 4xx/5xx
        $defaultHeaders = ['User-Agent' => 'FlujosDimensionBot/1.0'];
        $this->client = new Client(array_merge_recursive($config, [
            'headers' => $defaultHeaders,
            'http_errors' => false,
            'timeout' => 15
        ]));
    }

    /**
     * @throws RequestException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $apiName       = $options['api_name']      ?? 'unknown';
        $correlationId = $options['correlation_id'] ?? null;
        $batchId       = $options['batch_id']       ?? null;
        unset($options['api_name'], $options['correlation_id'], $options['batch_id']);

        if ($correlationId !== null) {
            $options['headers']['X-Correlation-ID'] = $correlationId;
        }

        $attempt = 0;
        $start   = microtime(true);

        do {
            /** @var ResponseInterface $response */
            $response = $this->client->request($method, $uri, $options);
            $status   = $response->getStatusCode();

            // 429 Rate-limit ⇒ back-off; 5xx (except 501/505) ⇒ retry corto
            $shouldRetry = in_array($status, [429, 500, 502, 503, 504], true)
                           && $attempt < $this->maxRetries;

            if (!$shouldRetry) {
                $elapsedMs = (int)((microtime(true) - $start) * 1000);
                $success   = $status >= 200 && $status < 300;
                $body      = $success ? null : (string)$response->getBody();

                if ($this->logger) {
                    $this->logger->info('api_request', [
                        'api_name'       => $apiName,
                        'endpoint'       => $uri,
                        'status_code'    => $status,
                        'response_time'  => $elapsedMs,
                        'success'        => $success,
                        'batch_id'       => $batchId,
                        'correlation_id' => $correlationId,
                    ]);
                }

                if ($this->db) {
                    $payload = json_encode([
                        'batch_id'       => $batchId,
                        'correlation_id' => $correlationId,
                        'error'          => $body,
                    ], JSON_UNESCAPED_UNICODE);

                    $stmt = $this->db->prepare(
                        'INSERT INTO api_monitoring (api_name, endpoint, response_time, status_code, success, error_message, timestamp)'
                        . ' VALUES (:api_name, :endpoint, :response_time, :status_code, :success, :error_message, CURRENT_TIMESTAMP)'
                    );
                    $stmt->execute([
                        ':api_name'      => $apiName,
                        ':endpoint'      => $uri,
                        ':response_time' => $elapsedMs,
                        ':status_code'   => $status,
                        ':success'       => $success ? 1 : 0,
                        ':error_message' => $payload,
                    ]);
                }

                return $response;
            }

            $attempt++;
            $retryAfter = (int)($response->getHeaderLine('Retry-After') ?: 0);
            $delayMs    = max($retryAfter * 1000, $this->baseDelayMs * (2 ** ($attempt - 1)));
            usleep($delayMs * 1000);
        } while (true);
    }
}
