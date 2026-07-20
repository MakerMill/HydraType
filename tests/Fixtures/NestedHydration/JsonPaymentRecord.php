<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

use MakerMill\HydraType\Mutators\HydrateAs;
use MakerMill\HydraType\Mutators\JsonValue;

final class JsonPaymentRecord
{
    public function __construct(
        #[JsonValue]
        #[HydrateAs(CardPayment::class)]
        private PaymentMethod $payment,
    ) {
    }

    public function payment(): PaymentMethod
    {
        return $this->payment;
    }
}
