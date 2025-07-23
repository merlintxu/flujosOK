<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class SyncController extends BaseController
{
    public function hourly(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }

    public function manual(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }

    public function status(): Response
    {
        return $this->jsonResponse(['success' => true, 'last_sync' => null]);
    }
}
