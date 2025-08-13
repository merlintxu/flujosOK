<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Core\Config;

/**
 * Manage runtime configuration values.
 */
class ConfigController extends BaseController
{
    /**
     * Return the full configuration array.
     */
    public function index(): Response
    {
        try {
            $config = Config::getInstance();
            $data   = $config->all();
            $sensitive = [
                'RINGOVER_API_KEY',
                'PIPEDRIVE_API_TOKEN',
                'OPENAI_API_KEY',
                'JWT_KEYS_CURRENT',
                'JWT_KEYS_PREVIOUS',
                'DB_PASS',
            ];
            foreach ($sensitive as $key) {
                if (isset($data[$key])) {
                    $data[$key] = '[hidden]';
                }
            }
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error loading configuration');
        }
    }

    /**
     * Update a single configuration key.
     */
    public function update(string $key): Response
    {
        try {
            $data = $this->request->all();
            $this->validate($data, ['value' => 'required|string']);

            $config = Config::getInstance();
            $config->set($key, $data['value']);

            return $this->successResponse(['updated' => $key, 'value' => $data['value']]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating configuration');
        }
    }

    /**
     * Update multiple configuration values at once.
     */
    public function batch(): Response
    {
        try {
            $payload = $this->request->getJsonBody() ?? [];
            $config = Config::getInstance();
            foreach ($payload as $k => $v) {
                $config->set($k, $v);
            }

            return $this->successResponse(['updated_keys' => array_keys($payload)]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating configuration batch');
        }
    }
}
