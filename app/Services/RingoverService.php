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
    private HttpClient  $http;
    /**
     * Configuration instance or array of raw values.
     * Accepting both types keeps backward compatibility with older
     * code that passed an array instead of the Config object.
     *
     * @var Config|array
     */
    private $config;
    private string $apiKey;
    private string $baseUrl;
    private int    $maxSize;
    private float  $lastRequestAt = 0.0;

    /**
     * Prepare HTTP client and configuration values.
     */
    public function __construct(HttpClient $http, Config|array $config)
    {
        $this->http   = $http;
        $this->config = $config;

        if ($config instanceof Config) {
            $this->apiKey  = $config->get('RINGOVER_API_KEY', '');
            $this->baseUrl = $config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2');
            $limitMb       = (int)$config->get('RINGOVER_MAX_RECORDING_MB', 100);
        } else {
            // allow using a plain associative array for configuration
            $this->apiKey  = $config['RINGOVER_API_KEY'] ?? '';
            $this->baseUrl = $config['RINGOVER_API_URL'] ?? 'https://public-api.ringover.com/v2';
            $limitMb       = (int)($config['RINGOVER_MAX_RECORDING_MB'] ?? 100);
        }

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
     * @param bool   $full   Include extra fields from Ringover (full=1)
     * @param ?string $fields Optional fields parameter, e.g. 'all'
     *
     * @return Generator<array<string,mixed>>
     */
    public function getCalls(\DateTimeInterface $since, bool $full = false, ?string $fields = null): Generator
    {
        $since = $since->setTimezone(new \DateTimeZone('UTC'));

        $limit     = 100;
        $offset    = 0;
        $page      = 1;
        $useOffset = true;
        $prevFirst = null;

        while (true) {
            $query = ['start_date' => $since->format(DATE_ATOM)];
            if ($full) {
                $query['full'] = 1;
            }
            if ($fields !== null) {
                $query['fields'] = $fields;
            }
            if ($useOffset) {
                $query['limit_offset'] = $offset;
                $query['limit_count']  = $limit;
            } else {
                $query['page']  = $page;
                $query['limit'] = $limit;
            }

            $body = $this->makeRequest('GET', "{$this->baseUrl}/calls", $query);
            $data = $body['data'] ?? [];

            if (empty($data)) {
                if ($useOffset) {
                    // Fallback to page based pagination
                    $useOffset = false;
                    $page      = 1;
                    $prevFirst = null;
                    continue;
                }
                break;
            }

            $firstId = $data[0]['id'] ?? null;
            if ($useOffset && $prevFirst !== null && $firstId === $prevFirst && $offset > 0) {
                // Offset parameters ignored by API, switch to page mode
                $useOffset = false;
                $page      = (int)($offset / $limit) + 1;
                $prevFirst = null;
                continue;
            }
            $prevFirst = $firstId;

            foreach ($data as $call) {
                yield $call;
            }

            if (count($data) < $limit) {
                break;
            }

            if ($useOffset) {
                $offset += $limit;
            } else {
                $page++;
            }
        }
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

        $lastState  = $call['last_state'] ?? null;
        $answered   = $call['is_answered'] ?? null;
        $status     = null;
        if ($lastState !== null || $answered !== null) {
            if (in_array($lastState, ['busy', 'failed'], true)) {
                $status = $lastState;
            } else {
                $status = (bool)$answered ? 'answered' : 'missed';
            }
        }

        $duration = $call['incall_duration'] ?? ($call['total_duration'] ?? null);

        return [
            'ringover_id'    => $call['id']            ?? null,
            'call_id'        => $call['call_id']       ?? null,
            'phone_number'   => $call['from_number']   ?? ($call['caller_number']   ?? null),
            'contact_number' => $call['to_number']     ?? ($call['contact_number']  ?? null),
            'caller_name'    => $call['from_name']     ?? ($call['caller_name']     ?? null),
            'contact_name'   => $call['to_name']       ?? ($call['contact_name']    ?? null),
            'direction'      => $direction,
            'start_time'     => $call['start_time']    ?? ($call['started_at']      ?? null),
            'total_duration' => $call['total_duration']?? null,
            'incall_duration'=> $call['incall_duration'] ?? null,
            'is_answered'    => $answered,
            'last_state'     => $lastState,
            'status'         => $status,
            'duration'       => $duration,
            'recording_url'  => $call['recording_url'] ?? ($call['recording']       ?? null),
            'voicemail_url'  => $call['voicemail_url'] ?? null,
        ];
    }

    /**
     * Download a recording URL into storage subdirectories.
     *
     * @return array{path:string,size:int,duration:int,format:string}
     */
    public function downloadRecording(string $url, string $subdir = 'recordings'): array
    {
        // Allow absolute paths for testing purposes
        if (str_contains($subdir, '/') || str_contains($subdir, '\\')) {
            $dir = $subdir;
        } else {
            $dir = "storage/{$subdir}";
        }

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

        $duration = (int)$head->getHeaderLine('X-Recording-Duration');
        if ($duration === 0) {
            $duration = (int)$head->getHeaderLine('X-Ringover-Duration');
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

        if ($size <= 0) {
            $size = filesize($real) ?: 0;
        }

        return [
            'path'     => $real,
            'size'     => $size,
            'duration' => $duration,
            'format'   => $extension,
        ];
    }

    public function downloadVoicemail(string $url): array
    {
        return $this->downloadRecording($url, 'voicemails');
    }
}

class RecordingException extends RuntimeException {}
class RecordingUnauthorizedException extends RecordingException {}
class RecordingTooLargeException extends RecordingException {}
class RecordingDownloadException extends RecordingException {}
class RecordingExtensionException extends RecordingException {}
