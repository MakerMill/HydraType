<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MinItems implements AssertionInterface
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Minimum item count must not be negative.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return "count({$valueExpression}) >= {$this->value}";
    }

    public function message(): string
    {
        return 'Number of items must be greater than or equal to ' . $this->value;
    }
}
