<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface BenchmarkHydrator
{
    /** @param array<string, mixed> $data */
    public function hydrate(array $data): BenchmarkTarget;

    /**
     * @param array<int, array<string, mixed>> $dataSet
     *
     * @return non-empty-list<BenchmarkTarget>
     */
    public function hydrateMany(array $dataSet): array;
}
