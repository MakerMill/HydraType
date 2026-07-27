<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer;

use MakerMill\HydraType\Tests\Consumer\Mutators\InvalidPhp;

final class InvalidPhpRecord
{
    public function __construct(
        #[InvalidPhp]
        private string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }
}
