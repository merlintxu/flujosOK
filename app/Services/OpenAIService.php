<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\HttpClient;
use RuntimeException;

/**
 * Lightweight wrapper for the OpenAI HTTP API.
 */
final class OpenAIService
{
    private const BASE = 'https://api.openai.com/v1';

    /**
     * Configure the HTTP client, API key and model.
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $apiKey,
        private readonly string     $model = 'gpt-4o-mini'
    ) {}

    /** @return array<string,mixed> */
    public function chat(array $messages, array $extra = []): array
    {
        $resp = $this->http->request('POST', self::BASE . '/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ],
            'json' => ['model' => $this->model, 'messages' => $messages] + $extra,
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException("OpenAI error {$resp->getStatusCode()}");
        }

        return json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
