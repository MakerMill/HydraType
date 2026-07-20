<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

final class PrivatePropertyTarget
{
    private int $value = 0;

    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    public function setRepeatedly(int $iterations): void
    {
        for ($i = 0; $i < $iterations; $i++) {
            $this->value = $i;
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'value' && is_int($value)) {
            $this->value = $value;
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
