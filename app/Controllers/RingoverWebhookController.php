<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Services\RingoverService;
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
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->request->getJsonBody() ?? [];
            $this->validate($data, [
                'call_id' => 'required|string',
                'recording_url' => 'required|string',
            ]);

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $info = $ringover->downloadRecording($data['recording_url'], 'recordings');

            $this->storeRecording($data['call_id'], $data['recording_url'], $info['path'], $info['duration']);

            return $this->successResponse(['stored' => true]);
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
                return $this->errorResponse('Invalid signature', 401);
            }

            $data = $this->request->getJsonBody() ?? [];
            $this->validate($data, [
                'call_id' => 'required|string',
                'voicemail_url' => 'required|string',
            ]);

            /** @var RingoverService $ringover */
            $ringover = $this->service(RingoverService::class);
            $info = $ringover->downloadVoicemail($data['voicemail_url']);

            $this->storeRecording($data['call_id'], $data['voicemail_url'], $info['path'], $info['duration']);

            return $this->successResponse(['stored' => true]);
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

    /**
     * Persist recording metadata for the given call.
     */
    private function storeRecording(string $ringoverId, string $url, string $path, int $duration): void
    {
        /** @var PDO $pdo */
        $pdo = $this->service('database');

        $pdo->beginTransaction();

        // Update calls table
        $stmt = $pdo->prepare('UPDATE calls SET recording_url = :url, recording_path = :path, has_recording = 1 WHERE ringover_id = :ringover_id');
        $stmt->execute([
            'url' => $url,
            'path' => $path,
            'ringover_id' => $ringoverId,
        ]);

        // Get internal call id
        $idStmt = $pdo->prepare('SELECT id FROM calls WHERE ringover_id = :ringover_id');
        $idStmt->execute(['ringover_id' => $ringoverId]);
        $callId = $idStmt->fetchColumn();

        if ($callId) {
            $size = filesize($path) ?: 0;
            $format = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp3';
            $insert = $pdo->prepare('INSERT INTO call_recordings (call_id, file_path, file_size, duration, format) VALUES (:call_id, :file_path, :file_size, :duration, :format)');
            $insert->execute([
                'call_id' => $callId,
                'file_path' => $path,
                'file_size' => $size,
                'duration' => $duration,
                'format' => $format,
            ]);
        }

        $pdo->commit();
    }
}
