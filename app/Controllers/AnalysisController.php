<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

class AnalysisController extends BaseController
{
    public function process(): Response
    {
        return $this->jsonResponse(['success' => true, 'batch_id' => 'batch_1']);
    }

    public function batchStatus(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'batch_id' => $id, 'status' => 'queued']);
    }

    public function sentimentBatch(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }

    public function keywords(): Response
    {
        return $this->jsonResponse(['success' => true, 'data' => []]);
    }
}
