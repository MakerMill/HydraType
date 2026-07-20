<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration;

final class NestedCountry
{
    private string $countryCode;

    public function checksum(): int
    {
        return strlen($this->countryCode);
    }
}
