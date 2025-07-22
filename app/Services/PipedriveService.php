<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Infrastructure\Http\HttpClient;
use RuntimeException;

final class PipedriveService
{
    private const BASE = 'https://api.pipedrive.com/v1';

    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $token
    ) {}

    public function findPersonByPhone(string $phone): ?int
    {
        $resp = $this->http->request('GET', self::BASE . '/persons/search', [
            'query' => [
                'term'      => $phone,
                'item_type' => 'person',
                'fields'    => 'phone',
                'api_token' => $this->token,
            ],
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException('Pipedrive search error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $data['data']['items'][0]['item']['id'] ?? null;
    }

    /** @return int Deal-ID */
    public function createOrUpdateDeal(array $payload): int
    {
        $resp = $this->http->request('POST', self::BASE . '/deals', [
            'query' => ['api_token' => $this->token],
            'json'  => $payload,
        ]);

        if ($resp->getStatusCode() !== 201) {
            throw new RuntimeException('Pipedrive deal error');
        }

        $data = json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return (int)$data['data']['id'];
    }
}
