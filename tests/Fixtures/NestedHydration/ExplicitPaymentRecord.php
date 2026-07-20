<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

use MakerMill\HydraType\Mutators\HydrateAs;

final class ExplicitPaymentRecord
{
    public function __construct(
        #[HydrateAs(CardPayment::class)]
        private PaymentMethod $payment,
    ) {
    }

    public function payment(): PaymentMethod
    {
        return $this->payment;
    }
}
