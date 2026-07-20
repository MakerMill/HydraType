<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class ImplicitAbstractRecord
{
    public function __construct(private AbstractPaymentMethod $payment)
    {
    }

    public function payment(): AbstractPaymentMethod
    {
        return $this->payment;
    }
}
