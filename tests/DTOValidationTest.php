<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use FlujosDimension\DTO\CallMetadataDTO;
use FlujosDimension\DTO\TranscriptionDTO;
use FlujosDimension\Support\Validator;

class DTOValidationTest extends TestCase
{
    public function testCallMetadataDtoValidates(): void
    {
        $dto = new CallMetadataDTO('123', 'inbound', 'answered', 30);
        $errors = Validator::validate($dto->toArray(), [
            'phone_number' => 'required|string',
            'direction'    => 'required|in:inbound,outbound',
            'status'       => 'required|string',
            'duration'     => 'integer',
        ]);
        $this->assertSame([], $errors);
    }

    public function testTranscriptionDtoRejectsInvalidData(): void
    {
        $errors = Validator::validate(['call_id' => 1], [
            'call_id' => 'required|integer',
            'text'    => 'required|string',
        ]);
        $this->assertArrayHasKey('text', $errors);
    }
}
