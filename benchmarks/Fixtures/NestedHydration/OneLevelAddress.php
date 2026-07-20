<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class OneLevelAddress
{
    private string $streetName;
    private string $postalCode;
    private string $countryCode;

    public function checksum(): int
    {
        return strlen($this->streetName) + strlen($this->postalCode) + strlen($this->countryCode);
    }
}
