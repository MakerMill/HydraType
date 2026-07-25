<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

use MakerMill\HydraType\Rules\Optional;
use stdClass;

final class UnsupportedPromotedDefaultRecord
{
    public function __construct(
        #[Optional]
        private object $value = new stdClass(),
    ) {
    }

    public function value(): object
    {
        return $this->value;
    }
}
