<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface BenchmarkTarget
{
    public function checksum(): int;
}
