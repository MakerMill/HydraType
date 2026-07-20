<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Interfaces;

/**
 * Compile-time extension point for transforming a property expression during extraction.
 *
 * It complements MutatorInterface for values that need a reversible representation without runtime extension dispatch.
 */
interface ExtractionMutatorInterface
{
    public function compileExtraction(string $inputExpression): string;
}
