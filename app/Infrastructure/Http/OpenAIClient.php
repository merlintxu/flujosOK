<?php
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use FlujosDimension\Core\Config;
use RuntimeException;

/**
 * HTTP client wrapper for the OpenAI API.
 */
final class OpenAIClient
{
    private const BASE = 'https://api.openai.com/v1';
    private string $model;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey
    ) {
        $this->model = Config::getInstance()->get('OPENAI_MODEL', 'gpt-4o-transcribe');
    }

    /**
     * Perform a chat completion request.
     *
     * @param array<int,array<string,mixed>> $messages
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
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
