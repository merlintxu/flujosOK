<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use FlujosDimension\Core\Config;
use FlujosDimension\Infrastructure\Http\HttpClient;
use RuntimeException;

class ApiService
{
    private HttpClient $http;
    private Config $config;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->http   = new HttpClient();
    }

    public function getRingoverCalls(int $limit = 50): array
    {
        $token = $this->config->get('RINGOVER_API_TOKEN');
        $base  = $this->config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2');

        $resp   = $this->http->request('GET', "$base/calls", [
            'headers' => ['Authorization' => $token],
            'query'   => ['limit' => $limit],
        ]);
        $status = $resp->getStatusCode();
        if ($status !== 200) {
            throw new RuntimeException("Ringover API error: $status");
        }

        return json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testRingoverApi(): array
    {
        try {
            $this->getRingoverCalls(1);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function testOpenAiApi(): array
    {
        $key  = $this->config->get('OPENAI_API_KEY');
        $base = $this->config->get('OPENAI_API_URL', 'https://api.openai.com/v1');

        try {
            $resp   = $this->http->request('GET', $base . '/models', [
                'headers' => ['Authorization' => "Bearer $key"],
            ]);
            $status = $resp->getStatusCode();
            if ($status !== 200) {
                return ['success' => false, 'status' => $status];
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function testPipedriveApi(): array
    {
        $token = $this->config->get('PIPEDRIVE_API_TOKEN');
        $base  = $this->config->get('PIPEDRIVE_API_URL', 'https://api.pipedrive.com/v1');

        try {
            $resp   = $this->http->request('GET', $base . '/users/me', [
                'query' => ['api_token' => $token],
            ]);
            $status = $resp->getStatusCode();
            if ($status !== 200) {
                return ['success' => false, 'status' => $status];
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAllApiStatus(): array
    {
        return [
            'ringover'  => $this->testRingoverApi(),
            'openai'    => $this->testOpenAiApi(),
            'pipedrive' => $this->testPipedriveApi(),
        ];
    }
}
