<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\RingoverClient;
use Generator;

/**
 * Domain service for handling Ringover call data.
 */
class CallService
{
    public function __construct(private readonly RingoverClient $client) {}

    /** @return array{success: bool, message?: string} */
    public function testConnection(): array
    {
        return $this->client->testConnection();
    }

    /**
     * Retrieve calls and map them to internal fields.
     *
     * @return Generator<int,array<string,mixed>>
     */
    public function getCalls(\DateTimeInterface $since, bool $full = false, ?string $fields = null, ?string $batchId = null): Generator
    {
        foreach ($this->client->getCalls($since, $full, $fields, $batchId) as $call) {
            yield $this->mapCallFields($call);
        }
    }

    /**
     * Map raw Ringover call data to internal call fields.
     *
     * @param array<string,mixed> $call
     * @return array<string,mixed>
     */
    public function mapCallFields(array $call): array
    {
        $directionRaw = $call['direction'] ?? ($call['type'] ?? null);
        $direction    = match ($directionRaw) {
            'in'  => 'inbound',
            'out' => 'outbound',
            default => $directionRaw,
        };

        $lastState = $call['last_state'] ?? ($call['status'] ?? null);
        $answered  = $call['is_answered'] ?? null;
        
        // Map Ringover states to internal status
        $status = match ($lastState) {
            'MISSED' => 'missed',
            'CANCELLED' => 'missed',
            'VOICEMAIL' => 'missed',
            'ANSWERED' => 'answered',
            'BUSY' => 'busy',
            'FAILED' => 'failed',
            default => $answered ? 'answered' : 'missed',
        };

        // Determine phone numbers based on direction
        $phoneNumber = $direction === 'inbound' 
            ? ($call['from_number'] ?? null)
            : ($call['to_number'] ?? null);
        
        $contactNumber = $call['contact_number'] ?? null;

        // Extract contact name from contact object if available
        $contactName = null;
        if (isset($call['contact']) && is_array($call['contact'])) {
            $contactName = $call['contact']['concat_name'] ?? 
                          ($call['contact']['firstname'] ?? '') . ' ' . ($call['contact']['lastname'] ?? '');
            $contactName = trim($contactName) ?: null;
        }

        $duration = $call['incall_duration'] ?? ($call['total_duration'] ?? null);

        return [
            'ringover_id'    => $call['cdr_id'] ?? $call['ringover_id'] ?? null,
            'call_id'        => $call['call_id'] ?? null,
            'phone_number'   => $phoneNumber,
            'contact_number' => $contactNumber,
            'caller_name'    => $call['from_name'] ?? ($call['caller_name'] ?? null),
            'contact_name'   => $contactName,
            'direction'      => $direction,
            'start_time'     => $call['start_time'] ?? ($call['started_at'] ?? null),
            'total_duration' => $call['total_duration'] ?? null,
            'incall_duration'=> $call['incall_duration'] ?? null,
            'status'         => $status,
            'duration'       => $duration,
            'recording_url'  => $call['record'] ?? null,
            'voicemail_url'  => $call['voicemail'] ?? null,
        ];
    }

    /**
     * Download a recording through the Ringover client.
     * @return array{path:string,size:int,duration:int,format:string}
     */
    public function downloadRecording(string $url, string $subdir = 'recordings'): array
    {
        return $this->client->downloadRecording($url, $subdir);
    }

    public function downloadVoicemail(string $url, string $dir = 'voicemails'): array
    {
        return $this->client->downloadVoicemail($url, $dir);
    }
}
