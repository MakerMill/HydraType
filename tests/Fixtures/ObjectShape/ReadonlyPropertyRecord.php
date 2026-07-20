<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class ReadonlyPropertyRecord
{
    public function __construct(
        private readonly int $id,
        private string $name,
    ) {
    }

    /** @return array{id: int, name: string} */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
