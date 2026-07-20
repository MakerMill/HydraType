<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\ExtractionTypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\TypeConstruct;

/** @internal */
final readonly class BackedEnumExtractionConverter implements ExtractionTypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool
    {
        return $property->getTypeConstruct() === TypeConstruct::EnumType;
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        if ($property->allowsNull()) {
            return "{$inputExpression}?->value";
        }

        return "{$inputExpression}->value";
    }
}
