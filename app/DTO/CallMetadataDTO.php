<?php

namespace FlujosDimension\DTO;

class CallMetadataDTO
{
    public function __construct(
        public string $phoneNumber,
        public string $direction,
        public string $status,
        public ?int $duration = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'phone_number' => $this->phoneNumber,
            'direction'    => $this->direction,
            'status'       => $this->status,
            'duration'     => $this->duration,
        ];
    }
}
