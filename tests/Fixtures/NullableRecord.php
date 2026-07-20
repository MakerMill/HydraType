<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\LeftTrim;

final class NullableRecord
{
    /** @param mixed $untyped */
    public function __construct(
        private int $id,
        #[LeftTrim]
        private ?string $displayName,
        private ?RecordState $recordState,
        private mixed $payload,
        private $untyped,
    ) {
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'recordState' => $this->recordState,
            'payload' => $this->payload,
            'untyped' => $this->untyped,
        ];
    }
}
