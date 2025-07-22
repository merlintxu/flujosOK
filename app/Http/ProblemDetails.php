<?php
declare(strict_types=1);

namespace FlujosDimension\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Respuesta RFC 7807 (application/problem+json)
 */
final class ProblemDetails extends JsonResponse
{
    public function __construct(int $status, string $title, string $detail = '')
    {
        parent::__construct(
            ['type' => 'about:blank', 'title' => $title, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json']
        );
    }
}
