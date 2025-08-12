<?php
namespace FlujosDimension\Jobs;

use FlujosDimension\Services\RingoverService;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Repositories\AsyncTaskRepository;
use FlujosDimension\Support\Validator;
use FlujosDimension\Core\Config;

class DownloadRecordingJob implements JobInterface
{
    public function __construct(
        private RingoverService $ringover,
        private CallRepository $calls,
        private AsyncTaskRepository $tasks
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        $errors = Validator::validate($payload, [
            'call_id'  => 'required|string',
            'url'      => 'required|format:url',
            'duration' => 'required|integer',
        ]);
        if ($errors) {
            throw new \InvalidArgumentException('Invalid job payload');
        }

        $url = $payload['url'];
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('Unsupported audio format');
        }

        $storageDir = dirname(__DIR__, 2) . '/storage';
        $recordingsDir = $storageDir . '/recordings';
        $info = $this->ringover->downloadRecording($url, $recordingsDir);

        $maxMb = (int) Config::getInstance()->get('RINGOVER_MAX_RECORDING_MB', 100);
        if ($info['size'] > $maxMb * 1024 * 1024) {
            @unlink($info['path']);
            throw new \RuntimeException('Recording exceeds max size');
        }

        $callId = $this->calls->findIdByRingoverId($payload['call_id']);
        if ($callId === null) {
            @unlink($info['path']);
            throw new \RuntimeException('Call not found');
        }

        $metadata = $info;
        $metadata['url'] = $url;
        $metadata['duration'] = $payload['duration'];
        $this->calls->addRecording($callId, $metadata);

        // Get correlation ID from payload
        $correlationId = $payload['correlation_id'] ?? bin2hex(random_bytes(16));
        $batchId = $payload['batch_id'] ?? null;

        // Queue transcription job with correlation ID
        $this->tasks->enqueue(TranscriptionJob::class, [
            'call_id' => $callId,
            'path'    => $info['path'],
            'format'  => $info['format'],
            'size'    => $info['size'],
            'correlation_id' => $correlationId,
            'batch_id' => $batchId,
        ], 5, $correlationId);
    }
}
