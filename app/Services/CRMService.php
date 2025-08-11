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

    public function findPersonByPhone(string $phone): ?int
    {
        return $this->client->findPersonByPhone($phone);
    }

    public function findOpenDeal(string $callId, ?string $phone = null): ?int
    {
        return $this->client->findOpenDeal($callId, $phone);
    }

    /** @return int Deal-ID */
    public function createOrUpdateDeal(array $payload): int
    {
        return $this->client->createOrUpdateDeal($payload);
    }
}
