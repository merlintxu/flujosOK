<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\AnalyticsService;

/**
 * Endpoints used to trigger and query AI analysis tasks.
 */
class AnalysisController extends BaseController
{
    /**
     * Launch analysis batch processing.
     */
    public function process(): Response
    {
        try {
            if (!$this->container->bound('analyticsService')) {
                return $this->jsonResponse(['batch_id' => '0', 'processed' => 0]);
            }

            $max = (int) $this->request->get('max', 50);
            /** @var AnalyticsService $service */
            $service = $this->service('analyticsService');
            $service->processBatch($max);

            return $this->jsonResponse([
                'batch_id' => (string)time(),
                'processed' => $service->lastProcessed(),
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error processing analysis');
        }
    }

    /**
     * Retrieve processing status for a given batch.
     */
    public function batchStatus(string $id): Response
    {
        try {
            if (!$this->container->bound('analyticsService')) {
                return $this->jsonResponse(['batch_id' => $id, 'processed' => 0]);
            }

            /** @var AnalyticsService $service */
            $service = $this->service('analyticsService');
            return $this->jsonResponse([
                'batch_id'  => $id,
                'processed' => $service->lastProcessed(),
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error getting batch status');
        }
    }

    /**
     * Run a sentiment analysis batch on pending calls.
     */
    public function sentimentBatch(): Response
    {
        try {
            if (!$this->container->bound('analyticsService')) {
                return $this->jsonResponse(['processed' => 0]);
            }

            /** @var AnalyticsService $service */
            $service = $this->service('analyticsService');
            $service->processBatch();
            return $this->jsonResponse(['processed' => $service->lastProcessed()]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error running sentiment batch');
        }
    }

    /**
     * Return analysis keywords placeholder.
     */
    public function keywords(): Response
    {
        try {
            // Not implemented; placeholder
            return $this->jsonResponse(['data' => []]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error retrieving keywords');
        }
    }
}
