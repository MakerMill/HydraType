<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\Assertions;

interface AssertionProfile
{
    public function checksum(): int;
}
