<?php

namespace FlujosDimension\DTO;

class AudioJobDTO
{
    public function __construct(
        public string $callId,
        public string $url,
        public int $duration
    ) {
    }

    public function toArray(): array
    {
        return [
            'call_id' => $this->callId,
            'url'     => $this->url,
            'duration'=> $this->duration,
        ];
    }
}
