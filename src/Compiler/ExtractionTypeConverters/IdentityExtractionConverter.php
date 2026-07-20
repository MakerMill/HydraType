<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\ExtractionTypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;

/** @internal */
final readonly class IdentityExtractionConverter implements ExtractionTypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool
    {
        return true;
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        return $inputExpression;
    }
}
