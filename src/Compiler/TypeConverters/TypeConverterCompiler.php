<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\TypeConverters;

use MakerMill\HydraType\PropertyAnalyzer;

/** @internal Selects a converter while source is compiled, never while an object is hydrated. */
final readonly class TypeConverterCompiler
{
    /** @var list<TypeConverterInterface> */
    private array $converters;

    public function __construct()
    {
        $this->converters = [
            new BooleanConverter(),
            new ScalarConverter(),
            new BackedEnumConverter(),
            new IdentityConverter(),
        ];
    }

    public function compile(
        PropertyAnalyzer $property,
        string $inputExpression,
        ?string $inputType = null,
    ): ?string {
        // A mutator that already guarantees the property type makes an additional generated cast unnecessary.
        if ($inputType === $property->getType()) {
            return $inputExpression;
        }

        // Converter dispatch happens once during compilation and leaves no lookup in the generated hydrator.
        foreach ($this->converters as $converter) {
            if ($converter->supports($property)) {
                return $converter->compile($property, $inputExpression);
            }
        }

        return null;
    }
}
