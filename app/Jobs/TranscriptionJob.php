<?php
namespace FlujosDimension\Jobs;

use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Support\Validator;
use FlujosDimension\Core\Config;

class TranscriptionJob implements JobInterface
{
    public function __construct(private CallRepository $calls) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        $errors = Validator::validate($payload, [
            'call_id' => 'required|integer',
            'path'    => 'required|string',
        ]);
        if ($errors) {
            throw new \InvalidArgumentException('Invalid job payload');
        }

        $path = $payload['path'];
        if (!is_file($path)) {
            throw new \RuntimeException('Audio file not found');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('Unsupported audio format');
        }

        $maxMb = (int) Config::getInstance()->get('RINGOVER_MAX_RECORDING_MB', 100);
        if (filesize($path) > $maxMb * 1024 * 1024) {
            throw new \RuntimeException('Recording exceeds max size');
        }

        // TODO: Integrate real transcription service. For now mark as processed.
        $this->calls->markAsProcessed((int)$payload['call_id']);
    }
}
