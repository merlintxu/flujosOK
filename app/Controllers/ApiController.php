<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;

class ApiController extends BaseController
{
    public function status(): Response
    {
        return $this->jsonResponse([
            'status' => 'ok',
            'timestamp' => date('c'),
        ]);
    }

    public function health(): Response
    {
        return $this->jsonResponse([
            'success' => true,
            'timestamp' => date('c'),
        ]);
    }
}
