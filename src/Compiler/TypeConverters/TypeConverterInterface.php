<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;

/** @internal */
interface TypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool;

    public function compile(PropertyAnalyzer $property, string $inputExpression): string;
}
