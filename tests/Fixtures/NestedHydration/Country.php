<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class Country
{
    public function __construct(private string $countryCode)
    {
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }
}
