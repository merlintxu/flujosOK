<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Core\Config;

class ConfigController extends BaseController
{
    public function index(): Response
    {
        try {
            $config = Config::getInstance();
            return $this->successResponse($config->all());
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error loading configuration');
        }
    }

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
