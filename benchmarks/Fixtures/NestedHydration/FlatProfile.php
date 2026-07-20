<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class FlatProfile implements BenchmarkProfile
{
    private int $id;
    private string $displayName;
    private string $streetName;
    private string $postalCode;
    private string $countryCode;

    public function checksum(): int
    {
        return $this->id
            + strlen($this->displayName)
            + strlen($this->streetName)
            + strlen($this->postalCode)
            + strlen($this->countryCode);
    }
}
