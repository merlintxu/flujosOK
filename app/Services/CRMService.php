<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\PipedriveClient;
use FlujosDimension\Repositories\CallRepository;
use RuntimeException;

/**
 * Domain service wrapping Pipedrive operations.
 */
class CRMService
{
    public function __construct(
        private readonly PipedriveClient $client,
        private readonly ?CallRepository $calls = null
    ) {}

    public function findPersonByPhone(string $phone, ?string $batchId = null, ?string $correlationId = null): ?int
    {
        return $this->client->findPersonByPhone($phone, $batchId, $correlationId);
    }

    public function findOpenDeal(string $callId, ?string $phone = null, ?string $batchId = null, ?string $correlationId = null): ?int
    {
        return $this->client->findOpenDeal($callId, $phone, $batchId, $correlationId);
    }

    /** @return int Deal-ID */
    public function createOrUpdateDeal(array $payload, ?string $batchId = null, ?string $correlationId = null): int
    {
        return $this->client->createOrUpdateDeal($payload, $batchId, $correlationId);
    }

    /**
     * Sync a call with the CRM.
     *
     * @return int|null Deal ID on success, null on failure
     */
    public function sync(int $callId, ?string $batchId = null, ?string $correlationId = null): ?int
    {
        $correlationId ??= bin2hex(random_bytes(16));

        if ($this->calls === null) {
            throw new RuntimeException('CallRepository not configured');
        }

        $call = $this->calls->find($callId);
        if (!$call) {
            $this->calls->logCrmSync($callId, 'error', 'call_not_found', $batchId, $correlationId);
            return null;
        }

        $phone    = $call['phone_number'] ?? null;
        $personId = $call['pipedrive_person_id'] ?? null;
        $dealId   = $call['pipedrive_deal_id'] ?? null;

        try {
            if ($personId === null && $phone) {
                $personId = $this->findPersonByPhone($phone, $batchId, $correlationId);
            }

            if ($dealId === null) {
                $dealId = $this->findOpenDeal((string)$callId, $phone, $batchId, $correlationId);
            }

            $fields = require dirname(__DIR__, 2) . '/config/pipedrive.php';
            $custom = [
                $fields['call_id_field'] => $callId,
            ];
            if (!empty($call['ai_sentiment'])) {
                $custom[$fields['sentiment_field']] = $call['ai_sentiment'];
            }
            if (!empty($call['ai_summary'])) {
                $custom[$fields['summary_field']] = $call['ai_summary'];
            }
            if (!empty($call['ai_transcription'])) {
                $custom[$fields['transcript_field']] = $call['ai_transcription'];
            }
            if (!empty($call['recording_url'])) {
                $custom[$fields['recording_field']] = $call['recording_url'];
            }
            if (!empty($call['duration'])) {
                $custom[$fields['duration_field']] = $call['duration'];
            }

            $payload = [
                'title'        => 'Call ' . $callId,
                'value'        => 0,
                'person_id'    => $personId,
                'custom_fields'=> $custom,
            ];
            if ($dealId !== null) {
                $payload['id'] = $dealId;
            }

            $dealId = $this->createOrUpdateDeal($payload, $batchId, $correlationId);

            $this->calls->updatePipedriveIds($callId, $personId, $dealId);
            $this->calls->logCrmSync($callId, 'success', null, $batchId, $correlationId);

            return $dealId;
        } catch (\Throwable $e) {
            $this->calls->logCrmSync($callId, 'error', $e->getMessage(), $batchId, $correlationId);
            return null;
        }
    }
}
