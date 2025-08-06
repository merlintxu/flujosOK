<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Core\Config;
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
    private int       $maxSize;

    /**
     * Prepare HTTP client and configuration values.
     */
    public function __construct(HttpClient $http, Config $config)
    {
        $this->http    = $http;
        $this->apiKey  = $config->get('RINGOVER_API_TOKEN', '');
        $this->baseUrl = $config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2');
        $limitMb       = (int)$config->get('RINGOVER_MAX_RECORDING_MB', 100);
        $this->maxSize = $limitMb * 1024 * 1024;
    }

    /**
     * Perform a lightweight request to verify API connectivity.
     *
     * @return array{success: bool}
     */
    public function testConnection(): array
    {
        try {
            $resp = $this->http->request('HEAD', "{$this->baseUrl}/calls", [
                'headers' => ['Authorization' => $this->apiKey],
            ]);
            return ['success' => $resp->getStatusCode() === 200];
        } catch (\Throwable) {
            return ['success' => false];
        }
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

        $path     = parse_url($url, PHP_URL_PATH) ?: '';
        $basename = basename($path);

        // sanitize directory traversal characters
        $basename = str_replace(['..', '/', '\\'], '', $basename);

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $allowed   = ['mp3', 'wav', 'ogg', 'm4a'];
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RuntimeException('Invalid recording extension');
        }

        $filename = $dir . '/' . $basename;

        $body = $resp->getBody();
        $fp   = fopen($filename, 'wb');
        $total = 0;

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $total += strlen($chunk);
            if ($total > $this->maxSize) {
                fclose($fp);
                unlink($filename);
                throw new RuntimeException('Recording exceeds size limit');
            }
            fwrite($fp, $chunk);
        }

        fclose($fp);
        return $filename;
    }
}
