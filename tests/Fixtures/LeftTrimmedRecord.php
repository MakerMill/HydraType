<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\LeftTrim;

final class LeftTrimmedRecord
{
    public function __construct(#[LeftTrim] private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
