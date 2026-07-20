<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

use MakerMill\HydraType\Rules\Optional;

final class OptionalUninitializedRecord
{
    #[Optional]
    private string $name;

    public function initialize(string $name): void
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
