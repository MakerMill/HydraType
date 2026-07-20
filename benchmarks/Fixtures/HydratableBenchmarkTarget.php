<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface HydratableBenchmarkTarget extends BenchmarkTarget
{
    /** @param array<string, int> $data */
    public function hydrate(array $data): void;
}
