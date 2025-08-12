<?php

namespace FlujosDimension\Jobs;

use GuzzleHttp\Client;
use PDO;

class DownloadRecordingJob
{
    public function __construct(
        private PDO $pdo,
        private Client $http
    ) {}

    public function handle(string $callId, string $url): void
    {
        $dir = __DIR__ . '/../../storage/recordings';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $target = $dir . "/{$callId}.mp3";
        $tmp = $target . '.part';

        $resp = $this->http->get($url, ['timeout' => 60]);
        file_put_contents($tmp, (string) $resp->getBody());
        rename($tmp, $target);

        $stmt = $this->pdo->prepare("UPDATE calls SET recording_path=?, has_recording=1 WHERE call_id=?");
        $stmt->execute([$target, $callId]);
    }
}
