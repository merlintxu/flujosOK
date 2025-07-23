<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;

class TokenController extends BaseController
{
    public function generate(): Response
    {
        $token = bin2hex(random_bytes(16));
        return $this->jsonResponse(['token' => $token]);
    }

    public function verify(): Response
    {
        $token = $this->request->input('token');
        $valid = is_string($token) && $token !== '';
        return $this->jsonResponse(['valid' => $valid]);
    }

    public function revoke(): Response
    {
        return $this->jsonResponse(['revoked' => true]);
    }
}
