<?php

namespace FlujosDimension\DTO;

class PipedriveUpdateDTO
{
    public function __construct(
        public int|string $callId,
        public ?int $dealId = null,
        public ?int $contactId = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'call_id'   => $this->callId,
            'deal_id'   => $this->dealId,
            'contact_id'=> $this->contactId,
        ];
    }
}
