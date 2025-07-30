<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

/**
 * Endpoints for report generation and download.
 */
class ReportController extends BaseController
{
    /**
     * Kick off report creation.
     */
    public function generate(): Response
    {
        return $this->jsonResponse(['success' => true, 'report_id' => 'report_1']);
    }

    /**
     * Check report generation status.
     */
    public function status(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'report_id' => $id, 'status' => 'generating']);
    }

    /**
     * Provide a download link once ready.
     */
    public function download(string $id): Response
    {
        return $this->jsonResponse(['success' => true, 'download' => $id]);
    }

    /**
     * Schedule periodic report generation.
     */
    public function schedule(): Response
    {
        return $this->jsonResponse(['success' => true]);
    }
}
