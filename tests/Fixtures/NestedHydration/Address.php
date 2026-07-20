<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class Address
{
    public function __construct(
        private string $streetName,
        private Country $country,
    ) {
    }

    public function streetName(): string
    {
        return $this->streetName;
    }

    public function country(): Country
    {
        return $this->country;
    }
}
