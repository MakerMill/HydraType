<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Round implements MutatorInterface
{
    public function __construct(
        private int $precision = 0,
        private int $mode = PHP_ROUND_HALF_UP,
    ) {
        if (!in_array($mode, [PHP_ROUND_HALF_UP, PHP_ROUND_HALF_DOWN, PHP_ROUND_HALF_EVEN, PHP_ROUND_HALF_ODD], true)) {
            throw new InvalidArgumentException('Round mode must be a PHP_ROUND_HALF_* constant.');
        }
    }

    public function compile(string $inputExpression): string
    {
        return "round((float) {$inputExpression}, {$this->precision}, {$this->mode})";
    }

    public function outputType(): string
    {
        return Type::Float->value;
    }
}
