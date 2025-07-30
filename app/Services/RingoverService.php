<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Core\Container;
use FlujosDimension\Infrastructure\Http\HttpClient;
use Generator;
use RuntimeException;

/**
 * Servicio Ringover con paginación completa.
 */
class RingoverService
{
    private HttpClient $http;
    private string     $apiKey;
    private string     $baseUrl;

    /**
     * Prepare HTTP client and configuration values.
     */
    public function __construct(Container $c)
    {
        $this->http    = $c->resolve('httpClient');
        $config        = $c->resolve('config');
        $this->apiKey  = $config['RINGOVER_API_TOKEN'] ?? '';
        $this->baseUrl = $config['RINGOVER_API_URL']  ?? 'https://public-api.ringover.com/v2';
    }

    /**
     * Devuelve TODAS las llamadas creadas a partir de $since (UTC).  Generator → baja memoria.
     *
     * @return Generator<array<string,mixed>>
     */
    public function getCalls(\DateTimeInterface $since): Generator
    {
        $uri   = "{$this->baseUrl}/calls";
        $query = [
            'date_start' => $since->format('Y-m-d\TH:i:sP'),
            'limit'      => 1000,
        ];

        do {
            $resp = $this->http->request('GET', $uri, [
                'headers' => ['Authorization' => $this->apiKey],
                'query'   => $query,
            ]);

            if ($resp->getStatusCode() !== 200) {
                throw new RuntimeException("Ringover error: {$resp->getStatusCode()}");
            }

            $body = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);

            foreach ($body['data'] ?? [] as $call) {
                yield $call;
            }

            $link = $resp->getHeaderLine('Link');
            if (preg_match('#<([^>]+)>;\s*rel="next"#', $link, $m)) {
                $uri   = $m[1];   // siguiente página ya con querystring
                $query = [];
            } else {
                $uri = null;
            }
        } while ($uri);
    }

    /**
     * Download a recording URL into storage/recordings.
     * @return string Local path
     */
    public function downloadRecording(string $url, string $dir = 'storage/recordings'): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $resp = $this->http->request('GET', $url, ['headers' => ['Authorization' => $this->apiKey]]);
        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException('Failed to download recording');
        }

        $filename = $dir . '/' . basename(parse_url($url, PHP_URL_PATH));
        file_put_contents($filename, (string) $resp->getBody());
        return $filename;
    }
}
