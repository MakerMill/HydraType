<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\ExtractionTypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;

/** @internal */
interface ExtractionTypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool;

    public function compile(PropertyAnalyzer $property, string $inputExpression): string;
}
