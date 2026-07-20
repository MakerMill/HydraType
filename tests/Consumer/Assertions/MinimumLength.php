<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MinimumLength implements AssertionInterface
{
    public function __construct(private int $length)
    {
        if ($this->length < 1) {
            throw new InvalidArgumentException('Minimum length must be at least one.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return "strlen({$valueExpression}) >= {$this->length}";
    }

    public function message(): string
    {
        return "Value must contain at least {$this->length} bytes.";
    }
}
