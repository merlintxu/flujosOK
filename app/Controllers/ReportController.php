<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class ReportController extends BaseController
{
    public function generate(): Response
    {
        return $this->jsonResponse(['success' => true, 'report_id' => 'report_1']);
    }

    public function status(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'report_id' => $id, 'status' => 'generating']);
    }

    public function download(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'download' => $id]);
    }

    public function schedule(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }
}
