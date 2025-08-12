<?php

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\RingoverClient;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Jobs\DownloadRecordingJob;
use GuzzleHttp\Client;

class SyncService
{
    public function __construct(
        private RingoverClient $ringover,
        private CallRepository $calls
    ) {}

    public function importRingover(string $sinceIso, ?string $untilIso = null, int $limit = 100): array
    {
        $offset = 0; $inserted = 0; $updated = 0; $seen = 0;

        do {
            $payload = $this->ringover->getCalls([
                'start_date' => $sinceIso,
                'end_date'   => $untilIso,
                'limit'      => $limit,
                'offset'     => $offset,
            ]);
            $list = $payload['call_list'] ?? [];

            foreach ($list as $c) {
                $row = $this->mapRingoverToCalls($c);
                [$i,$u] = $this->calls->upsertByCallId($row);
                $inserted += $i; $updated += $u; $seen++;

                if (!empty($row['recording_url'])) {
                    $job = new DownloadRecordingJob($this->calls->getConnection(), new Client());
                    $job->handle($row['call_id'], $row['recording_url']);
                }
            }

            $offset += $limit;
        } while (count($list) === $limit);

        return compact('seen','inserted','updated');
    }

    private function mapRingoverToCalls(array $c): array
    {
        $dir = $c['direction'] ?? null;
        return [
            'call_id'          => $c['call_id']         ?? null,
            'ringover_id'      => $c['cdr_id']          ?? null,
            'channel_id'       => $c['channel_id']      ?? null,
            'direction'        => ($dir==='in'||$dir==='inbound') ? 'inbound'
                                   : (($dir==='out'||$dir==='outbound') ? 'outbound' : null),
            'status'           => $c['last_state']      ?? ($c['status'] ?? 'pending'),
            'start_time'       => $c['start_time']      ?? null,
            'answered_time'    => $c['answered_time']   ?? null,
            'end_time'         => $c['end_time']        ?? null,
            'incall_duration'  => (int)($c['incall_duration']  ?? 0),
            'total_duration'   => (int)($c['total_duration']   ?? 0),
            'queue_duration'   => (int)($c['queue_duration']   ?? 0),
            'ringing_duration' => (int)($c['ringing_duration'] ?? 0),
            'contact_number'   => $c['contact_number']  ?? null,
            'phone_number'     => $c['contact_number']  ?? null,
            'recording_url'    => $c['record']          ?? ($c['record_url'] ?? null),
            'voicemail_url'    => $c['voicemail']       ?? ($c['voicemail_url'] ?? null),
            'has_recording'    => empty($c['record']) ? 0 : 1,
        ];
    }
}
