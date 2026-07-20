<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class AssertedAddress
{
    public function __construct(private AssertedCountry $country)
    {
    }

    public function country(): AssertedCountry
    {
        return $this->country;
    }
}
