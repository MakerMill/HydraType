<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

interface BenchmarkProfile
{
    public function checksum(): int;
}
