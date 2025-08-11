<?php

namespace FlujosDimension\DTO;

class TranscriptionDTO
{
    public function __construct(
        public int|string $callId,
        public string $text,
        public ?float $confidence = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'call_id'    => $this->callId,
            'text'       => $this->text,
            'confidence' => $this->confidence,
        ];
    }
}
