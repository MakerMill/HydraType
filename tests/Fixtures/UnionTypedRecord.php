<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class UnionTypedRecord
{
    private int|string $value;

    public function __construct(int|string $value)
    {
        $this->value = $value;
    }

    public function value(): int|string
    {
        return $this->value;
    }
}
