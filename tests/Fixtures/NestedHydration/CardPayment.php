<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class CardPayment implements PaymentMethod
{
    public function __construct(private string $reference)
    {
    }

    public function reference(): string
    {
        return $this->reference;
    }
}
