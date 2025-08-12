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

class RecordingException extends RuntimeException {}
class RecordingUnauthorizedException extends RecordingException {}
class RecordingTooLargeException extends RecordingException {}
class RecordingDownloadException extends RecordingException {}
class RecordingExtensionException extends RecordingException {}
