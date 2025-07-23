<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class CallsController extends BaseController
{
    public function index(): Response
    {
        return $this->jsonResponse(['success' => true, 'data' => []]);
    }

    public function show(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'id' => $id]);
    }

    public function store(): Response
    {
        return $this->jsonResponse(['success' => true], 201);
    }

    public function update(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'updated' => $id]);
    }

    public function destroy(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'deleted' => $id]);
    }
}
