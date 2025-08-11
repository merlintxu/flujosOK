<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\OpenAIClient;
use RuntimeException;

/**
 * Domain service providing high level analysis using OpenAI.
 */
class AnalysisService
{
    private int $usedTokens = 0;

    public function __construct(
        private readonly OpenAIClient $client,
        private readonly int $tokenLimit = 100000
    ) {}

    /**
     * Send chat messages to OpenAI while controlling token usage.
     *
     * @param array<int,array<string,mixed>> $messages
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function chat(array $messages, array $extra = []): array
    {
        if ($this->usedTokens >= $this->tokenLimit) {
            throw new RuntimeException('OpenAI token limit exceeded');
        }

        $resp = $this->client->chat($messages, $extra);
        $this->usedTokens += $resp['usage']['total_tokens'] ?? 0;

        if ($this->usedTokens > $this->tokenLimit) {
            throw new RuntimeException('OpenAI token limit exceeded');
        }

        return $resp;
    }

    public function tokensUsed(): int
    {
        return $this->usedTokens;
    }
}
