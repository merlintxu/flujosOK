<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\ProblemDetails;
use App\Services\AnalyticsService;
use App\Services\RingoverService;
use App\Services\PipedriveService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CallController
{
    public function __construct(
        private readonly RingoverService   $ringover,
        private readonly AnalyticsService  $analytics,
        private readonly PipedriveService  $pipedrive
    ) {}

    /**
     * Endpoint /api/v1/sync/hourly  – lanzado por n8n o cron.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $since = new DateTimeImmutable('-1 hour');
            $new   = 0;

            foreach ($this->ringover->getCalls($since) as $call) {
                // Aquí insertas en BD vía repositorio/model (omitido por brevedad)
                $new++;
            }

            // procesa TODO lo pendiente, máx 50
            $this->analytics->processPendingCallsBatch(50);

            return new JsonResponse(['inserted' => $new, 'status' => 'ok'], 200);
        } catch (\Throwable $e) {
            return new ProblemDetails(502, 'Upstream Error', $e->getMessage());
        }
    }
}
