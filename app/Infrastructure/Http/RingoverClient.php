<?php

namespace FlujosDimension\Infrastructure\Http;

use FlujosDimension\Core\Config;
use GuzzleHttp\Client;

class RingoverClient
{
    private string $base = 'https://public-api.ringover.com/v2';

    public function __construct(private Client $http, private Config $cfg) {}

    /**
     * @param array{start_date?:string,end_date?:string,limit?:int,offset?:int,team_id?:string,user_id?:string} $params
     * @return array
     */
    public function getCalls(array $params): array
    {
        $query = array_filter([
            'start_date' => $params['start_date'] ?? null,
            'end_date'   => $params['end_date']   ?? null,
            'limit'      => $params['limit']      ?? 100,
            'offset'     => $params['offset']     ?? 0,
            'team_id'    => $params['team_id']    ?? null,
            'user_id'    => $params['user_id']    ?? null,
        ], fn($v) => $v !== null);

        $res = $this->http->get($this->base.'/calls', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->cfg->get('RINGOVER_API_KEY'),
                'Accept'        => 'application/json',
            ],
            'query'   => $query,
            'timeout' => 30,
        ]);

        return json_decode((string) $res->getBody(), true) ?? [];
    }
}
