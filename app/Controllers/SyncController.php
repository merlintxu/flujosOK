<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Services\AnalyticsService;
use FlujosDimension\Repositories\CallRepository;

/**
 * Synchronisation routines between Ringover and the local database.
 */
class SyncController extends BaseController
{
    /**
     * Automatic hourly sync triggered by cron.
     */
    public function hourly(): Response
    {
        try {
            if (!$this->container->bound(RingoverService::class) || !$this->container->bound('callRepository')) {
                return $this->successResponse(['inserted' => 0]);
            }

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            /** @var CallRepository $repo */
            $repo     = $this->service('callRepository');
            /** @var AnalyticsService $analytics */
            $analytics = $this->container->bound('analyticsService') ? $this->service('analyticsService') : null;

            $since = new \DateTimeImmutable('-1 hour');
            $inserted = 0;
            foreach ($ringover->getCalls($since) as $call) {
                $repo->insertOrIgnore($call);
                $inserted++;
            }

            if ($analytics) {
                $analytics->processBatch();
            }
            $this->logActivity('sync_hourly', ['inserted' => $inserted]);

            return $this->successResponse(['inserted' => $inserted]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Hourly sync failed');
        }
    }

    /**
     * Manually start a sync for a custom time range.
     */
    public function manual(): Response
    {
        try {
            if (!$this->container->bound(RingoverService::class) || !$this->container->bound('callRepository')) {
                return $this->successResponse(['inserted' => 0]);
            }

            $sinceParam = $this->request->get('since', '-1 day');
            $since = new \DateTimeImmutable($sinceParam);

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            /** @var CallRepository $repo */
            $repo     = $this->service('callRepository');
            
            $inserted = 0;
            foreach ($ringover->getCalls($since) as $call) {
                $repo->insertOrIgnore($call);
                $inserted++;
            }

            $this->logActivity('sync_manual', ['inserted' => $inserted]);

            return $this->successResponse(['inserted' => $inserted]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Manual sync failed');
        }
    }

    /**
     * Return timestamp of the last synced call.
     */
    public function status(): Response
    {
        try {
            if (!$this->container->bound('callRepository')) {
                return $this->successResponse(['last_sync' => null]);
            }

            /** @var CallRepository $repo */
            $repo = $this->service('callRepository');
            $latest = $repo->callsNotInCrm();
            $lastSync = null;
            if (!empty($latest)) {
                $last = end($latest);
                $lastSync = $last['created_at'] ?? null;
            }
            return $this->successResponse(['last_sync' => $lastSync]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error getting sync status');
        }
    }
}
