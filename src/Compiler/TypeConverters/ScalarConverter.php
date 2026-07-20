<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\Type;

/** @internal */
final readonly class ScalarConverter implements TypeConverterInterface
{
    private const SUPPORTED_TYPES = [
        Type::Array->value,
        Type::Float->value,
        Type::Int->value,
        Type::Object->value,
        Type::String->value,
    ];

    public function supports(PropertyAnalyzer $property): bool
    {
        return in_array($property->getType(), self::SUPPORTED_TYPES, true);
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        return "({$property->getType()}) {$inputExpression}";
    }
}
