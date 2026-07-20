<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Interfaces;

/**
 * Compile-time extension point for transforming an input expression before property assignment.
 *
 * Implementations contribute PHP source and their output type; mutator objects are not dispatched during hydration.
 */
interface MutatorInterface
{
    public function compile(string $inputExpression): string;

    public function outputType(): string;
}
