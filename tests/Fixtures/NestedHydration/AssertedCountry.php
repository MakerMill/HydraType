<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

use MakerMill\HydraType\Assertions\NotEmpty;

final class AssertedCountry
{
    public function __construct(
        #[NotEmpty]
        private string $countryCode,
    ) {
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }
}
