<?php

namespace FlujosDimension\DTO;

class NLPInsightsDTO
{
    public function __construct(
        public int|string $callId,
        public ?string $summary = null,
        public ?string $sentiment = null,
        public array $keywords = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'call_id'  => $this->callId,
            'summary'  => $this->summary,
            'sentiment'=> $this->sentiment,
            'keywords' => $this->keywords,
        ];
    }
}
