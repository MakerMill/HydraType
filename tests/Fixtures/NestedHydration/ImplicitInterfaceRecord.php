<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class ImplicitInterfaceRecord
{
    public function __construct(private PaymentMethod $payment)
    {
    }

    public function payment(): PaymentMethod
    {
        return $this->payment;
    }
}
