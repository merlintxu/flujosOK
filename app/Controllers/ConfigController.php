<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class ConfigController extends BaseController
{
    public function index(): Response
    {
        return $this->jsonResponse(['success' => true, 'configurations' => []]);
    }

    public function update(string $key): Response
    {
        return $this->jsonResponse(['success' => true, 'updated' => $key]);
    }

    public function batch(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }
}
