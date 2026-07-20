<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Clamp implements MutatorInterface
{
    public function __construct(private int|float $minimum, private int|float $maximum)
    {
        if ($minimum > $maximum) {
            throw new InvalidArgumentException('Clamp minimum must not be greater than its maximum.');
        }
    }

    public function compile(string $inputExpression): string
    {
        $minimum = PhpLiteral::value($this->minimum);
        $maximum = PhpLiteral::value($this->maximum);

        return "max({$minimum}, min({$maximum}, (float) {$inputExpression}))";
    }

    public function outputType(): string
    {
        return Type::Float->value;
    }
}
