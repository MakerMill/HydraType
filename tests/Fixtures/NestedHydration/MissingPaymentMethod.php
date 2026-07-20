<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

// The conditional declaration lets static analysis resolve the attribute target while leaving it absent at runtime.
if (defined('HYDRATYPE_TEST_DECLARE_MISSING_NESTED_TARGET')) {
    final class MissingPaymentMethod implements PaymentMethod
    {
        public function reference(): string
        {
            return '';
        }
    }
}
