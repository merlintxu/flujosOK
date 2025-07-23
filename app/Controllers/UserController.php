<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class UserController extends BaseController
{
    public function index(): Response
    {
        return $this->jsonResponse(['success' => true, 'data' => []]);
    }

    public function create(): Response
    {
        return $this->jsonResponse(['success' => true], 201);
    }

    public function update(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'updated' => $id]);
    }

    public function permissions(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'permissions_updated' => $id]);
    }
}
