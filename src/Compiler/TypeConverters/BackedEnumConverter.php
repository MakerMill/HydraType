<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use LogicException;
use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\TypeConstruct;
use ReflectionEnum;

/** @internal */
final readonly class BackedEnumConverter implements TypeConverterInterface
{
    public function supports(PropertyAnalyzer $property): bool
    {
        return $property->getTypeConstruct() === TypeConstruct::EnumType;
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        $enumType = $property->getType();
        if (!enum_exists($enumType)) {
            throw new LogicException("Enum '{$enumType}' does not exist.");
        }
        $backingType = (new ReflectionEnum($enumType))->getBackingType()?->getName();
        if ($backingType === null) {
            throw new LogicException("Enum '{$enumType}' is not backed.");
        }

        return "\\{$enumType}::tryFrom(($backingType) {$inputExpression})";
    }
}
