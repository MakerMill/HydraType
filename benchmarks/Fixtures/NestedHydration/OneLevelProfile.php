<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class OneLevelProfile implements BenchmarkProfile
{
    private int $id;
    private string $displayName;
    private OneLevelAddress $address;

    public function checksum(): int
    {
        return $this->id + strlen($this->displayName) + $this->address->checksum();
    }
}
