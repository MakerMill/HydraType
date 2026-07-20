<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class CycleB
{
    public function __construct(private ?CycleA $cycleA)
    {
    }

    public function cycleA(): ?CycleA
    {
        return $this->cycleA;
    }
}
