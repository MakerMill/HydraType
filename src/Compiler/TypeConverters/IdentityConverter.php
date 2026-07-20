<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\Type;
use MakerMill\HydraType\TypeConstruct;

/** @internal */
final readonly class IdentityConverter implements TypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool
    {
        return $property->getTypeConstruct() === TypeConstruct::Undefined
            || $property->getType() === Type::Mixed->value;
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        return $inputExpression;
    }
}
