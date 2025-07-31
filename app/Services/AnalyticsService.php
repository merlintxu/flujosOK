<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Repositories\CallRepository;

/**
 * Handles AI-based analysis of call records.
 */
final class AnalyticsService
{
    private int $lastProcessed = 0;

    /**
     * Set up the repository and OpenAI client.
     */
    public function __construct(
        private readonly CallRepository $repo,
        private readonly OpenAIService  $openai
    ) {}

    /**
     * Procesa hasta $max llamadas pendientes (marca pending_analysis=1).
     */
    public function processBatch(int $max = 50): void
    {
        $pending = $this->repo->pending($max);
        if ($pending === []) {
            $this->lastProcessed = 0;
            return;
        }

        $messages = array_map(
            static fn(array $c) => [
                'role'    => 'user',
                'content' => "Analiza la llamada disponible en {$c['recording_url']}.
                               Devuelve resumen, sentimiento y 5 keywords.",
            ],
            $pending
        );

        $resp = $this->openai->chat($messages, ['temperature' => 0.3]);

        $this->repo->saveBatch($pending, $resp['choices']);
        $this->lastProcessed = count($pending);
    }

    /**
     * Number of calls processed in the last batch.
     */
    public function lastProcessed(): int
    {
        return $this->lastProcessed;
    }

    /**
     * Retrieve dashboard data for a given period.
     */
    public function getDashboardData(string $period): array
    {
        return ['success' => true, 'data' => []];
    }

    /**
     * Clear cached analytics data.
     */
    public function clearCache(): void
    {
        $cacheDir = sys_get_temp_dir() . '/fd-cache';
        $cache = new \FlujosDimension\Core\CacheManager($cacheDir);
        $cache->deletePattern('*');
    }
}
