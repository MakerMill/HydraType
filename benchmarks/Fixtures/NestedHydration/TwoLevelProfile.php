<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class TwoLevelProfile implements BenchmarkProfile
{
    private int $id;
    private string $displayName;
    private TwoLevelAddress $address;

    public function checksum(): int
    {
        return $this->id + strlen($this->displayName) + $this->address->checksum();
    }
}
