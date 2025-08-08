<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
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

            $data = $this->request->getJsonBody() ?? [];
            $this->validate($data, [
                'call_id' => 'required|string',
                'recording_url' => 'required|string',
                'duration' => 'required|integer',
            ]);

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $info = $ringover->downloadRecording($data['recording_url'], 'recordings');

            /** @var CallRepository $repo */
            $repo = $this->service(CallRepository::class);
            $callId = $repo->findIdByRingoverId($data['call_id']);
            if ($callId === null) {
                return $this->errorResponse('Call not found', 404);
            }

            $metadata = $info;
            $metadata['url'] = $data['recording_url'];
            $metadata['duration'] = (int)$data['duration'];

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

            $data = $this->request->getJsonBody() ?? [];
            $this->validate($data, [
                'call_id' => 'required|string',
                'voicemail_url' => 'required|string',
                'duration' => 'required|integer',
            ]);

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $info = $ringover->downloadVoicemail($data['voicemail_url']);

            /** @var CallRepository $repo */
            $repo = $this->service(CallRepository::class);
            $callId = $repo->findIdByRingoverId($data['call_id']);
            if ($callId === null) {
                return $this->errorResponse('Call not found', 404);
            }

            $metadata = $info;
            $metadata['url'] = $data['voicemail_url'];
            $metadata['duration'] = (int)$data['duration'];

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
