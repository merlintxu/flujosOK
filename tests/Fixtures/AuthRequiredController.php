<?php
namespace FlujosDimension\Controllers;
use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;

class AuthRequiredController extends BaseController
{
    public function __construct(Container $c, Request $r)
    {
        parent::__construct($c, $r);
    }

    public function secure(): Response
    {
        $payload = $this->requireAuth();
        return $this->jsonResponse(['user_id' => $payload['user_id'] ?? null]);
    }
}
