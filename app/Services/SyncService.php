<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use DateTimeInterface;

/**
 * High level orchestrator combining call import, analysis and CRM push.
 */
final class SyncService
{
    public function __construct(
        private readonly CallService $calls,
        private readonly AnalysisService $analysis,
        private readonly CRMService $crm
    ) {}

    /**
     * Synchronize calls since the given date.
     * Returns number of calls processed.
     */
    public function sync(DateTimeInterface $since): int
    {
        $count = 0;
        foreach ($this->calls->getCalls($since) as $call) {
            // Placeholder for analysis and CRM integration.
            $count++;
        }
        return $count;
    }
}
