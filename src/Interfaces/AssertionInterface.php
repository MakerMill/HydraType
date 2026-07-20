<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Interfaces;

/**
 * Compile-time extension point for guarding a transformed property value before assignment.
 *
 * Implementations return a PHP expression that is true for an accepted value. Assertion objects are not dispatched
 * during hydration; the condition and failure message are embedded directly in the generated writer.
 */
interface AssertionInterface
{
    public function compileCondition(string $valueExpression): string;

    public function message(): string;
}
