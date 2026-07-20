<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface WriterLifecycleHydrator
{
    /** @param array<string, mixed> $data */
    public function hydrate(array $data): WriterLifecycleTarget;

    /**
     * @param array<int, array<string, mixed>> $dataSet
     *
     * @return non-empty-list<WriterLifecycleTarget>
     */
    public function hydrateMany(array $dataSet): array;
}
