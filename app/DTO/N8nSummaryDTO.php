<?php

namespace FlujosDimension\DTO;

class N8nSummaryDTO
{
    public function __construct(
        public int|string $callId,
        public string $summary,
        public array $metadata = [],
        public array $insights = [],
        public array $recordings = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'call_id'    => $this->callId,
            'summary'    => $this->summary,
            'metadata'   => $this->metadata,
            'insights'   => $this->insights,
            'recordings' => $this->recordings,
        ];
    }
}
