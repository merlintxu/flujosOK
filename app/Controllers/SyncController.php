<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Services\AnalyticsService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Repositories\SyncHistoryRepository;

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
            if (!$this->container->bound(RingoverService::class)
                || !$this->container->bound('callRepository')
                || !$this->container->bound('syncHistoryRepository')) {
                return $this->successResponse(['inserted' => 0]);
            }

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            /** @var CallRepository $repo */
            $repo     = $this->service('callRepository');
            /** @var AnalyticsService $analytics */
            $analytics = $this->container->bound('analyticsService') ? $this->service('analyticsService') : null;

            /** @var SyncHistoryRepository $history */
            $history = $this->service('syncHistoryRepository');

            $since = $history->getLastSyncedAt() ?? new \DateTimeImmutable('-1 hour');
            $last   = $since;
            $inserted = 0;
            foreach ($ringover->getCalls($since) as $call) {
                $mapped = $ringover->mapCallFields($call);
                $repo->insertOrIgnore($mapped);
                $inserted++;
                $callTime = isset($mapped['start_time']) ? new \DateTimeImmutable($mapped['start_time']) : $since;
                if ($callTime > $last) {
                    $last = $callTime;
                }
            }

            if ($inserted > 0) {
                $history->updateLastSyncedAt($last);
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
            if (!$this->container->bound(RingoverService::class)
                || !$this->container->bound('callRepository')
                || !$this->container->bound('syncHistoryRepository')) {
                return $this->successResponse(['inserted' => 0]);
            }

            $history = $this->service('syncHistoryRepository');
            $sinceParam = $this->request->get('since');
            $since = $sinceParam ? new \DateTimeImmutable($sinceParam) : ($history->getLastSyncedAt() ?? new \DateTimeImmutable('-1 day'));
            $last = $since;

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            /** @var CallRepository $repo */
            $repo     = $this->service('callRepository');

            $inserted = 0;
            foreach ($ringover->getCalls($since) as $call) {
                $mapped = $ringover->mapCallFields($call);
                $repo->insertOrIgnore($mapped);
                $inserted++;
                $callTime = isset($mapped['start_time']) ? new \DateTimeImmutable($mapped['start_time']) : $since;
                if ($callTime > $last) {
                    $last = $callTime;
                }
            }

            if ($inserted > 0) {
                $history->updateLastSyncedAt($last);
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
            if (!$this->container->bound('syncHistoryRepository')) {
                return $this->successResponse(['last_sync' => null]);
            }

            /** @var SyncHistoryRepository $history */
            $history = $this->service('syncHistoryRepository');
            $ts = $history->getLastSyncedAt();
            $lastSync = $ts ? $ts->format('Y-m-d H:i:s') : null;

            return $this->successResponse(['last_sync' => $lastSync]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error getting sync status');
        }
    }
}
