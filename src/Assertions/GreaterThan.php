<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class GreaterThan implements AssertionInterface
{
    public function __construct(private int|float $value)
    {
    }

    public function compileCondition(string $valueExpression): string
    {
        return "{$valueExpression} > " . PhpLiteral::value($this->value);
    }

    public function message(): string
    {
        return 'Value must be greater than ' . $this->value;
    }
}
