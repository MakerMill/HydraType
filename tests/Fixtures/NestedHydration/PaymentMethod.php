<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

interface PaymentMethod
{
    public function reference(): string;
}
