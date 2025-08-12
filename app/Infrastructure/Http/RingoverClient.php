<?php
/**
 * HTTP client for the Ringover API with pagination and retry support.
 */
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use FlujosDimension\Core\Config;
use GuzzleHttp\Client as GuzzleClient;
use Generator;
use JsonException;
use RuntimeException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * HTTP client for the Ringover API with pagination and retry support.
 */
final class RingoverClient
{
    private HttpClient $http;
    private string $apiKey;
    private string $baseUrl;
    private int    $maxSize;
    private float  $lastRequestAt = 0.0;

    /**
     * @param HttpClient|GuzzleClient $http
     * @param Config|array $config
     */
    public function __construct(HttpClient|GuzzleClient $http, Config|array $config)
    {
        // Si recibimos un GuzzleClient, lo envolvemos en nuestro HttpClient
        if ($http instanceof GuzzleClient) {
            $this->http = new HttpClient($http);
        } else {
            $this->http = $http;
        }

        if ($config instanceof Config) {
            $this->apiKey  = $config->get('RINGOVER_API_KEY', '');
            $this->baseUrl = $config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2');
            $limitMb       = (int)$config->get('RINGOVER_MAX_RECORDING_MB', 100);
        } else {
            $this->apiKey  = $config['RINGOVER_API_KEY'] ?? '';
            $this->baseUrl = $config['RINGOVER_API_URL'] ?? 'https://public-api.ringover.com/v2';
            $limitMb       = (int)($config['RINGOVER_MAX_RECORDING_MB'] ?? 100);
        }

        $this->maxSize = $limitMb * 1024 * 1024;
    }

    /** @return array{success: bool, message?: string} */
    public function testConnection(): array
    {
        try {
            $response = $this->http->get($this->baseUrl . '/calls', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'query' => ['limit' => 1],
                'service' => 'ringover'
            ]);
            
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => "HTTP {$statusCode}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Retrieve calls from Ringover API with pagination support.
     *
     * @return Generator<int,array<string,mixed>>
     */
    public function getCalls(\DateTimeInterface $since, bool $full = false, ?string $fields = null, ?string $batchId = null): Generator
    {
        $query = [
            'start_date' => $since->format('Y-m-d H:i:s'),
            'limit' => 100
        ];

        if ($full) {
            $query['full'] = 'true';
        }
        if ($fields) {
            $query['fields'] = $fields;
        }

        $offset = 0;
        do {
            $query['offset'] = $offset;
            
            $response = $this->http->get($this->baseUrl . '/calls', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'query' => $query,
                'service' => 'ringover',
                'correlation_id' => $batchId
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException("Ringover API error: HTTP {$statusCode}");
            }

            $body = $this->http->getBodyAsString($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Ringover API');
            }
            
            $calls = $data['data'] ?? [];

            foreach ($calls as $call) {
                yield $call;
            }

            $hasMore = count($calls) === $query['limit'];
            $offset += $query['limit'];
            
        } while ($hasMore);
    }

    /**
     * Download a recording file.
     * @return array{path:string,size:int,duration:int,format:string}
     */
    public function downloadRecording(string $url, string $subdir = 'recordings'): array
    {
        return $this->downloadMedia($url, $subdir);
    }

    /**
     * Download a voicemail file.
     */
    public function downloadVoicemail(string $url, string $dir = 'voicemails'): array
    {
        return $this->downloadMedia($url, $dir);
    }

    /**
     * Download media file (recording or voicemail).
     * @return array{path:string,size:int,duration:int,format:string}
     */
    private function downloadMedia(string $url, string $subdir): array
    {
        $response = $this->http->get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'service' => 'ringover'
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException("Failed to download media: HTTP {$statusCode}");
        }

        $body = $this->http->getBodyAsString($response);
        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'media_' . time();
        $dir = __DIR__ . '/../../../storage/' . $subdir;
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $filename;
        file_put_contents($path, $body);

        return [
            'path' => $path,
            'size' => filesize($path),
            'duration' => 0, // Would need media analysis
            'format' => pathinfo($filename, PATHINFO_EXTENSION)
        ];
    }
}

// Exception classes moved outside the main class
class RecordingException extends RuntimeException {}
class RecordingUnauthorizedException extends RecordingException {}
class RecordingTooLargeException extends RecordingException {}
class RecordingDownloadException extends RecordingException {}
class RecordingExtensionException extends RecordingException {}
