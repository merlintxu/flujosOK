<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente HTTP genérico con back-off exponencial y soporte Retry-After.
 */
final class HttpClient
{
    private Client $client;

    public function __construct(
        array $config = [],
        private readonly int $maxRetries = 5,
        private readonly int $baseDelayMs = 500
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
        $attempt = 0;

        do {
            /** @var ResponseInterface $response */
            $response = $this->client->request($method, $uri, $options);
            $status   = $response->getStatusCode();

            // 429 Rate-limit ⇒ back-off; 5xx (except 501/505) ⇒ retry corto
            $shouldRetry = in_array($status, [429, 500, 502, 503, 504], true)
                           && $attempt < $this->maxRetries;

            if (!$shouldRetry) {
                return $response;
            }

            $attempt++;
            $retryAfter = (int)($response->getHeaderLine('Retry-After') ?: 0);
            $delayMs    = max($retryAfter * 1000, $this->baseDelayMs * (2 ** ($attempt - 1)));
            usleep($delayMs * 1000);
        } while (true);
    }
}
