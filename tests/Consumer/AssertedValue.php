<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer;

use MakerMill\HydraType\Assertions\NotEmpty;
use MakerMill\HydraType\Mutators\Trim;
use MakerMill\HydraType\Tests\Consumer\Assertions\MinimumLength;

final class AssertedValue
{
    public function __construct(
        #[Trim]
        #[NotEmpty]
        #[MinimumLength(3)]
        private string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }
}
