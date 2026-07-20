<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

use MakerMill\HydraType\Mutators\HydrateAs;

final class InternalTargetRecord
{
    public function __construct(
        #[HydrateAs(\stdClass::class)]
        private object $value,
    ) {
    }

    public function value(): object
    {
        return $this->value;
    }
}
