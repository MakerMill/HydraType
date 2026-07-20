<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Positive implements AssertionInterface
{
    public function compileCondition(string $valueExpression): string
    {
        return "{$valueExpression} > 0";
    }

    public function message(): string
    {
        return 'Value must be positive';
    }
}
