<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Rules\Optional;

final class EmptyInputRecord
{
    private ?string $name = null;

    #[Optional]
    private int $count = 1;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /** @return array{name: string|null, count: int} */
    public function values(): array
    {
        return [
            'name' => $this->name,
            'count' => $this->count,
        ];
    }
}
