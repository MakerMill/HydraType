<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Between implements AssertionInterface
{
    public function __construct(private int|float $from, private int|float $to)
    {
        if ($from > $to) {
            throw new InvalidArgumentException('Between lower bound must not be greater than its upper bound.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        $from = PhpLiteral::value($this->from);
        $to = PhpLiteral::value($this->to);

        return "{$valueExpression} >= {$from} && {$valueExpression} <= {$to}";
    }

    public function message(): string
    {
        return 'Value must be between ' . $this->from . ' and ' . $this->to;
    }
}
