<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;

/**
 * Simple endpoints to verify API health and status.
 */
class ApiController extends BaseController
{
    /**
     * Return a short status payload for uptime checks.
     */
    public function status(): Response
    {
        return $this->jsonResponse([
            'status' => 'ok',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Basic health check used by monitoring services.
     */
    public function health(): Response
    {
        return $this->jsonResponse([
            'success' => true,
            'timestamp' => date('c'),
        ]);
    }
}
