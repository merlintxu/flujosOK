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
        $this->apiKey  = $config->get('RINGOVER_API_KEY', '');
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
        $directionRaw = $call['direction'] ?? ($call['type'] ?? null);
        $direction    = match ($directionRaw) {
            'in'  => 'inbound',
            'out' => 'outbound',
            default => $directionRaw,
        };

        $lastState = $call['last_state'] ?? null;
        $isAnswered = (bool)($call['is_answered'] ?? false);
        $status = in_array($lastState, ['busy', 'failed'], true)
            ? $lastState
            : ($isAnswered ? 'answered' : 'missed');

        return [
            'ringover_id'  => $call['id']         ?? null,
            'phone_number' => $call['from_number'] ?? ($call['to_number'] ?? null),
            'direction'    => $direction,
            'status'       => $status,
            'duration'     => $call['incall_duration'] ?? ($call['total_duration'] ?? 0),
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
        $options = [
            'headers'         => ['Authorization' => $this->apiKey],
            'allow_redirects' => ['max' => 5, 'track_redirects' => true],
        ];

        $head = $this->http->request('HEAD', $url, $options);
        if (in_array($head->getStatusCode(), [401, 403], true)) {
            $head = $this->http->request('HEAD', $url, ['allow_redirects' => ['max' => 5, 'track_redirects' => true]]);
        }

        $status = $head->getStatusCode();
        if ($status !== 200) {
            if (in_array($status, [401, 403], true)) {
                throw new RecordingUnauthorizedException('Unauthorized recording request');
            }
            throw new RecordingDownloadException("Failed to retrieve recording headers: {$status}");
        }

        $size = (int)$head->getHeaderLine('Content-Length');
        if ($size > 0 && $size > $this->maxSize) {
            throw new RecordingTooLargeException('Recording exceeds size limit');
        }

        $effectiveUrl = $head->getHeaderLine('X-Guzzle-Effective-Url');
        if ($effectiveUrl === '') {
            $history = $head->getHeader('X-Guzzle-Redirect-History');
            $effectiveUrl = $history ? end($history) : $url;
        }

        $path     = parse_url($effectiveUrl, PHP_URL_PATH) ?: '';
        $basename = basename($path);

        // sanitize directory traversal characters
        $basename = str_replace(['..', '/', '\\'], '', $basename);

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $allowed   = ['mp3', 'wav', 'ogg', 'm4a'];
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RecordingExtensionException('Invalid recording extension');
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $dir . '/' . $basename;

        $resp = $this->http->request('GET', $url, $options);
        if (in_array($resp->getStatusCode(), [401, 403], true)) {
            $resp = $this->http->request('GET', $url, ['allow_redirects' => ['max' => 5, 'track_redirects' => true]]);
        }
        $status = $resp->getStatusCode();
        if ($status !== 200) {
            if (in_array($status, [401, 403], true)) {
                throw new RecordingUnauthorizedException('Unauthorized recording request');
            }
            throw new RecordingDownloadException('Failed to download recording');
        }

        $body   = $resp->getBody();
        $fp     = fopen($filename, 'wb');
        $total  = 0;

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $total += strlen($chunk);
            if ($total > $this->maxSize) {
                fclose($fp);
                unlink($filename);
                throw new RecordingTooLargeException('Recording exceeds size limit');
            }
            fwrite($fp, $chunk);
        }

        fclose($fp);
        $real = realpath($filename);
        if ($real === false) {
            throw new RecordingDownloadException('Failed to resolve recording path');
        }
        return $real;
    }
}

class RecordingException extends RuntimeException {}
class RecordingUnauthorizedException extends RecordingException {}
class RecordingTooLargeException extends RecordingException {}
class RecordingDownloadException extends RecordingException {}
class RecordingExtensionException extends RecordingException {}
