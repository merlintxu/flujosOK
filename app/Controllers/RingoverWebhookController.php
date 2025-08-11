<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\DTO\AudioJobDTO;
use FlujosDimension\Support\Validator;
use PDO;

/**
 * Handle Ringover webhook callbacks.
 */
class RingoverWebhookController extends BaseController
{
    /**
     * Recording available webhook.
     */
    public function recordAvailable(): Response
    {
        try {
            if (!$this->isValidSignature()) {
                $this->logger->error('Invalid Ringover signature');
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->normalizeInput($this->request->getJsonBody() ?? []);
            $dto  = new AudioJobDTO(
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
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $storageDir   = dirname(__DIR__, 2) . '/storage';
            $recordingsDir = $storageDir . '/recordings';
            $info = $ringover->downloadRecording($dto->url, $recordingsDir);

            /** @var CallRepository $repo */
            $repo = $this->service(CallRepository::class);
            $callId = $repo->findIdByRingoverId($data['call_id']);
            if ($callId === null) {
                return $this->errorResponse('Call not found', 404);
            }

            $metadata = $info;
            $metadata['url'] = $dto->url;
            $metadata['duration'] = $dto->duration;

            $repo->addRecording($callId, $metadata);

            /** @var PDO $pdo */
            $pdo = $this->service('database');
            $recordingId = (int)$pdo->lastInsertId();

            return $this->successResponse([
                'path' => $info['path'],
                'recording_id' => $recordingId,
            ]);
        } catch (\Exception $e) {
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
                $this->logger->error('Invalid Ringover signature');
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->normalizeInput($this->request->getJsonBody() ?? []);
            $dto  = new AudioJobDTO(
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
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $storageDir    = dirname(__DIR__, 2) . '/storage';
            $voicemailsDir = $storageDir . '/voicemails';
            $info = $ringover->downloadVoicemail($dto->url, $voicemailsDir);

            /** @var CallRepository $repo */
            $repo = $this->service(CallRepository::class);
            $callId = $repo->findIdByRingoverId($data['call_id']);
            if ($callId === null) {
                return $this->errorResponse('Call not found', 404);
            }

            $metadata = $info;
            $metadata['url'] = $dto->url;
            $metadata['duration'] = $dto->duration;

            $repo->addRecording($callId, $metadata);

            /** @var PDO $pdo */
            $pdo = $this->service('database');
            $recordingId = (int)$pdo->lastInsertId();

            return $this->successResponse([
                'path' => $info['path'],
                'recording_id' => $recordingId,
            ]);
        } catch (\Exception $e) {
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
