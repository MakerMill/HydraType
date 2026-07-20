<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler\ExtractionTypeConverters;

use LogicException;
use MakerMill\HydraType\PropertyAnalyzer;

/** @internal Selects an extraction converter during compilation, leaving no runtime dispatch. */
final readonly class ExtractionTypeConverterCompiler
{
    /** @var list<ExtractionTypeConverterInterface> */
    private array $converters;

    public function __construct()
    {
        $this->converters = [
            new BackedEnumExtractionConverter(),
            new IdentityExtractionConverter(),
        ];
    }

    public function compile(PropertyAnalyzer $property, string $inputExpression): string
    {
        foreach ($this->converters as $converter) {
            if ($converter->supports($property)) {
                return $converter->compile($property, $inputExpression);
            }
        }

        throw new LogicException("No extraction converter supports property '{$property->getName()}'.");
    }
}
