<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use Countable;
use Iterator;

final class IntersectionTypedRecord
{
    private Countable&Iterator $value;

    public function __construct(Countable&Iterator $value)
    {
        $this->value = $value;
    }

    public function value(): Countable&Iterator
    {
        return $this->value;
    }
}
