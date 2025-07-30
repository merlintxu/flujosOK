<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

/**
 * Receive external webhook calls.
 */
class WebhookController extends BaseController
{
    /**
     * Register a new webhook endpoint.
     */
    public function create(): Response
    {
        return $this->jsonResponse(['success' => true, 'id' => 'webhook_1']);
    }
}
