<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MaxLength implements AssertionInterface
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Maximum length must not be negative.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return "strlen({$valueExpression}) <= {$this->value}";
    }

    public function message(): string
    {
        return 'Length must be less than or equal to ' . $this->value;
    }
}
