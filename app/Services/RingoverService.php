<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Core\Config;
use FlujosDimension\Infrastructure\Http\HttpClient;
use Generator;
use JsonException;
use RuntimeException;

/**
 * Servicio Ringover con paginación completa.
 */
class RingoverService
{
    private HttpClient $http;
    private Config     $config;
    private string     $apiKey;
    private string     $baseUrl;
    private int        $maxSize;
    private float      $lastRequestAt = 0.0;

    /**
     * Prepare HTTP client and configuration values.
     */
    public function __construct(HttpClient $http, Config $config)
    {
        $this->http    = $http;
        $this->config  = $config;
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
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function makeRequest(string $method, string $uri, array $query = []): array
    {
        $now     = microtime(true);
        $elapsed = $now - $this->lastRequestAt;
        if ($elapsed < 0.5) {
            usleep((int)((0.5 - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);

        $attempt = 0;
        $delay   = 0.5;

        do {
            $resp   = $this->http->request($method, $uri, [
                'headers'         => ['Authorization' => $this->apiKey],
                'query'           => $query,
                'timeout'         => 10,
                'connect_timeout' => 5,
            ]);
            $status = $resp->getStatusCode();

            if ($status >= 200 && $status < 300) {
                try {
                    $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new RuntimeException('Invalid JSON from Ringover', 0, $e);
                }
                if (!is_array($data)) {
                    throw new RuntimeException('Invalid JSON from Ringover');
                }
                return $data;
            }

            if (!in_array($status, [429, 500, 502, 503, 504], true) || $attempt >= 5) {
                throw new RuntimeException("Ringover error: {$status}");
            }

            $retryAfter = (int)$resp->getHeaderLine('Retry-After');
            $sleep      = max($retryAfter, $delay);
            usleep((int)($sleep * 1_000_000));
            $delay *= 2;
            $attempt++;
        } while (true);
    }

    /**
     * Devuelve TODAS las llamadas creadas a partir de $since.
     * El parámetro se convierte automáticamente a UTC antes de la consulta.
     * Generator → baja memoria.
     *
     * @return Generator<array<string,mixed>>
     */
    public function getCalls(\DateTimeInterface $since): Generator
    {
        $since = $since->setTimezone(new \DateTimeZone('UTC'));

        $page  = 1;
        $limit = 100;

        do {
            $body = $this->makeRequest('GET', "{$this->baseUrl}/calls", [
                'start_date' => $since->format(DATE_ATOM),
                'page'       => $page,
                'limit'      => $limit,
            ]);

            foreach ($body['data'] ?? [] as $call) {
                yield $call;
            }

            if (empty($body['data']) || count($body['data']) < $limit) {
                break;
            }

            $page++;
        } while (true);
    }

    /**
     * Map raw Ringover call data to internal call fields.
     *
     * @param array<string,mixed> $call
     * @return array<string,mixed>
     */
    public function mapCallFields(array $call): array
    {
        return [
            'ringover_id'  => $call['id']             ?? null,
            'phone_number' => $call['contact_number'] ?? ($call['from_number'] ?? ($call['to_number'] ?? null)),
            'direction'    => $call['direction']      ?? ($call['type'] ?? null),
            'status'       => $call['status']         ?? ($call['last_state'] ?? null),
            'duration'     => $call['duration']       ?? ($call['total_duration'] ?? 0),
            'recording_url'=> $call['recording_url']  ?? ($call['recording'] ?? null),
            'start_time'   => $call['start_time']     ?? ($call['started_at'] ?? null),
        ];
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
