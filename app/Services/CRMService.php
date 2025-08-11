<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\PipedriveClient;

/**
 * Domain service wrapping Pipedrive operations.
 */
class CRMService
{
    public function __construct(private readonly PipedriveClient $client) {}

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
}
