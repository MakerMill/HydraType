<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\Type;

/** @internal */
final readonly class BooleanConverter implements TypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool
    {
        return $property->getType() === Type::Bool->value;
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        return "match ($inputExpression) {"
            . "true, 1, '1', 't', 'Y', 'true', 'yes', 'on' => true,"
            . 'default => false,'
            . '}';
    }
}
