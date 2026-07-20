<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class EmptyStringToNull implements MutatorInterface
{
    public function compile(string $inputExpression): string
    {
        return "((\$hydraMutatorValue = (string) {$inputExpression}) === '' ? null : \$hydraMutatorValue)";
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
