<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class TwoLevelAddress
{
    private string $streetName;
    private string $postalCode;
    private NestedCountry $country;

    public function checksum(): int
    {
        return strlen($this->streetName) + strlen($this->postalCode) + $this->country->checksum();
    }
}
