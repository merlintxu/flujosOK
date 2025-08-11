<?php
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use RuntimeException;

/**
 * HTTP client wrapper for a subset of the Pipedrive API.
 */
final class PipedriveClient
{
    private const BASE = 'https://api.pipedrive.com/v1';

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $token
    ) {}

    public function findPersonByPhone(string $phone, ?string $batchId = null, ?string $correlationId = null): ?int
    {
        $resp = $this->http->request('GET', self::BASE . '/persons/search', [
            'query' => [
                'term'      => $phone,
                'item_type' => 'person',
                'fields'    => 'phone',
                'api_token' => $this->token,
            ],
            'api_name'       => 'Pipedrive',
            'batch_id'       => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException('Pipedrive search error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $data['data']['items'][0]['item']['id'] ?? null;
    }

    public function findOpenDeal(string $callId, ?string $phone = null, ?string $batchId = null, ?string $correlationId = null): ?int
    {
        $resp = $this->http->request('GET', self::BASE . '/deals/search', [
            'query' => [
                'term'      => $callId,
                'fields'    => 'custom_fields.Call_ID',
                'status'    => 'open',
                'api_token' => $this->token,
            ],
            'api_name'       => 'Pipedrive',
            'batch_id'       => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException('Pipedrive deal search error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $id   = $data['data']['items'][0]['item']['id'] ?? null;
        if ($id) {
            return (int)$id;
        }

        if ($phone === null || $phone === '') {
            return null;
        }

        $resp = $this->http->request('GET', self::BASE . '/deals/search', [
            'query' => [
                'term'      => $phone,
                'fields'    => 'phone',
                'status'    => 'open',
                'api_token' => $this->token,
            ],
            'api_name'       => 'Pipedrive',
            'batch_id'       => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException('Pipedrive deal search error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $data['data']['items'][0]['item']['id'] ?? null;
    }

    /**
     * Create or update a deal and return its ID.
     */
    public function createOrUpdateDeal(array $payload, ?string $batchId = null, ?string $correlationId = null): int
    {
        $resp = $this->http->request('POST', self::BASE . '/deals', [
            'query' => ['api_token' => $this->token],
            'json'  => $payload,
            'api_name'       => 'Pipedrive',
            'batch_id'       => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 201) {
            throw new RuntimeException('Pipedrive deal error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return (int)$data['data']['id'];
    }
}
