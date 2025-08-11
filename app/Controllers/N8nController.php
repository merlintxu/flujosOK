<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\DTO\N8nSummaryDTO;
use FlujosDimension\Repositories\CallRepository;

class N8nController extends BaseController
{
    public function getSummary(string $id): Response
    {
        try {
            $this->requireAuth();

            /** @var CallRepository $repo */
            $repo = $this->service('callRepository');
            $call = $repo->find((int)$id);
            if (!$call) {
                throw new NotFoundHttpException('Call not found');
            }

            $metadata = [
                'direction' => $call['direction'] ?? null,
                'status'    => $call['status'] ?? null,
                'duration'  => $call['duration'] ?? null,
                'start_time'=> $call['start_time'] ?? null,
            ];

            $insights = [
                'sentiment' => $call['ai_sentiment'] ?? null,
                'keywords'  => isset($call['ai_keywords']) && $call['ai_keywords'] !== null
                    ? array_filter(array_map('trim', explode(',', (string)$call['ai_keywords'])))
                    : [],
            ];

            $recordings = array_values(array_filter([
                $call['recording_url'] ?? null,
                $call['voicemail_url'] ?? null,
            ]));

            $dto = new N8nSummaryDTO(
                $call['id'],
                $call['ai_summary'] ?? '',
                $metadata,
                $insights,
                $recordings
            );

            return $this->successResponse($dto->toArray());
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error fetching summary');
        }
    }
}
