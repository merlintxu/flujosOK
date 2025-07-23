<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class WebhookController extends BaseController
{
    public function create(): Response
    {
        return $this->jsonResponse(['success' => true, 'id' => 'webhook_1']);
    }
}
