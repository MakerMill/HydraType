<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class CycleA
{
    public function __construct(private ?CycleB $cycleB)
    {
    }

    public function cycleB(): ?CycleB
    {
        return $this->cycleB;
    }
}
