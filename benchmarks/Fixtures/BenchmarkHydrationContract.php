<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface BenchmarkHydrationContract
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    public function renameArrayKeys(array $data): array;
}
