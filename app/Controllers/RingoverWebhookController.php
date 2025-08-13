<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Core\WebhookDeduplicator;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\DTO\AudioJobDTO;
use FlujosDimension\Support\Validator;
use PDO;

/**
 * Handle Ringover webhook callbacks with idempotency.
 */
class RingoverWebhookController extends BaseController
{
    private WebhookDeduplicator $deduplicator;

    public function __construct($container, $request)
    {
        parent::__construct($container, $request);
        
        // Initialize webhook deduplicator
        $db = $this->container->resolve('db');
        $logger = $this->container->resolve('logger');
        $this->deduplicator = new WebhookDeduplicator($db, [], $logger);
    }

    /**
     * Recording available webhook.
     */
    public function recordAvailable(): Response
    {
        try {
            if (!$this->isValidSignature()) {
                $this->logger->error('Invalid Ringover signature', [
                    'correlation_id' => $this->request->getCorrelationId()
                ]);
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->normalizeInput($this->request->getJsonBody() ?? []);
            $correlationId = $this->request->getCorrelationId();
            
            // Check for duplicate webhook
            $deduplicationResult = $this->deduplicator->shouldProcess(
                'ringover_call',
                $data,
                $correlationId,
                3600 // 1 hour TTL
            );
            
            if (!$deduplicationResult['should_process']) {
                $this->logger->info('Duplicate Ringover webhook ignored', [
                    'correlation_id' => $correlationId,
                    'deduplication_key' => $deduplicationResult['deduplication_key'],
                    'original_processed_at' => $deduplicationResult['original_processed_at'] ?? null
                ]);
                
                return $this->successResponse([
                    'queued' => false,
                    'reason' => 'duplicate',
                    'deduplication_key' => $deduplicationResult['deduplication_key']
                ]);
            }

            $dto = new AudioJobDTO(
                $data['call_id'] ?? '',
                $data['recording_url'] ?? '',
                isset($data['duration']) ? (int)$data['duration'] : 0
            );

            $errors = Validator::validate($dto->toArray(), [
                'call_id' => 'required|string',
                'url'     => 'required|format:url',
                'duration'=> 'required|integer',
            ]);
            
            if ($errors) {
                // Mark as failed so it can be retried
                $this->deduplicator->markFailed(
                    $deduplicationResult['deduplication_key'],
                    'Validation failed: ' . implode(', ', $errors)
                );
                
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var \FlujosDimension\Repositories\AsyncTaskRepository $tasks */
            $tasks = $this->service(\FlujosDimension\Repositories\AsyncTaskRepository::class);
            
            // Add correlation ID and deduplication key to job data
            $jobData = $dto->toArray();
            $jobData['correlation_id'] = $correlationId;
            $jobData['deduplication_key'] = $deduplicationResult['deduplication_key'];
            
            $tasks->enqueue(\FlujosDimension\Jobs\DownloadRecordingJob::class, $jobData, 5, $correlationId);

            $this->logger->info('Ringover recording webhook processed', [
                'correlation_id' => $correlationId,
                'deduplication_key' => $deduplicationResult['deduplication_key'],
                'call_id' => $data['call_id'] ?? 'unknown'
            ]);

            return $this->successResponse([
                'queued' => true,
                'deduplication_key' => $deduplicationResult['deduplication_key']
            ]);
            
        } catch (\Exception $e) {
            // Mark as failed if we have deduplication key
            if (isset($deduplicationResult['deduplication_key'])) {
                $this->deduplicator->markFailed(
                    $deduplicationResult['deduplication_key'],
                    $e->getMessage()
                );
            }
            
            return $this->handleError($e, 'Error processing recording webhook');
        }
    }

    /**
     * Voicemail available webhook.
     */
    public function voicemailAvailable(): Response
    {
        try {
            if (!$this->isValidSignature()) {
                $this->logger->error('Invalid Ringover signature', [
                    'correlation_id' => $this->request->getCorrelationId()
                ]);
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->normalizeInput($this->request->getJsonBody() ?? []);
            $correlationId = $this->request->getCorrelationId();
            
            // Check for duplicate webhook
            $deduplicationResult = $this->deduplicator->shouldProcess(
                'ringover_voicemail',
                $data,
                $correlationId,
                3600 // 1 hour TTL
            );
            
            if (!$deduplicationResult['should_process']) {
                $this->logger->info('Duplicate Ringover voicemail webhook ignored', [
                    'correlation_id' => $correlationId,
                    'deduplication_key' => $deduplicationResult['deduplication_key'],
                    'original_processed_at' => $deduplicationResult['original_processed_at'] ?? null
                ]);
                
                return $this->successResponse([
                    'queued' => false,
                    'reason' => 'duplicate',
                    'deduplication_key' => $deduplicationResult['deduplication_key']
                ]);
            }

            $dto = new AudioJobDTO(
                $data['call_id'] ?? '',
                $data['voicemail_url'] ?? '',
                isset($data['duration']) ? (int)$data['duration'] : 0
            );

            $errors = Validator::validate($dto->toArray(), [
                'call_id' => 'required|string',
                'url'     => 'required|format:url',
                'duration'=> 'required|integer',
            ]);
            
            if ($errors) {
                // Mark as failed so it can be retried
                $this->deduplicator->markFailed(
                    $deduplicationResult['deduplication_key'],
                    'Validation failed: ' . implode(', ', $errors)
                );
                
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var \FlujosDimension\Repositories\AsyncTaskRepository $tasks */
            $tasks = $this->service(\FlujosDimension\Repositories\AsyncTaskRepository::class);
            
            // Add correlation ID and deduplication key to job data
            $jobData = $dto->toArray();
            $jobData['correlation_id'] = $correlationId;
            $jobData['deduplication_key'] = $deduplicationResult['deduplication_key'];
            
            $tasks->enqueue(\FlujosDimension\Jobs\DownloadRecordingJob::class, $jobData, 5, $correlationId);

            $this->logger->info('Ringover voicemail webhook processed', [
                'correlation_id' => $correlationId,
                'deduplication_key' => $deduplicationResult['deduplication_key'],
                'call_id' => $data['call_id'] ?? 'unknown'
            ]);

            return $this->successResponse([
                'queued' => true,
                'deduplication_key' => $deduplicationResult['deduplication_key']
            ]);
            
        } catch (\Exception $e) {
            // Mark as failed if we have deduplication key
            if (isset($deduplicationResult['deduplication_key'])) {
                $this->deduplicator->markFailed(
                    $deduplicationResult['deduplication_key'],
                    $e->getMessage()
                );
            }
            
            return $this->handleError($e, 'Error processing voicemail webhook');
        }
    }

    /**
     * Validate webhook signature using configured secret.
     */
    private function isValidSignature(): bool
    {
        $secret = $this->config('RINGOVER_WEBHOOK_SECRET');
        $signature = $this->request->getHeader('x-ringover-signature');
        $payload = $this->request->getBody() ?? '';

        if (!$secret || !$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    // Legacy storeRecording method removed in favor of CallRepository->addRecording
}

