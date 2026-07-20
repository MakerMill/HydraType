<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class MatchesPattern implements AssertionInterface
{
    public function __construct(private string $pattern)
    {
        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING);
        try {
            $isValid = preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$isValid) {
            throw new InvalidArgumentException('MatchesPattern pattern must be a valid PCRE pattern.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return 'preg_match(' . PhpLiteral::string($this->pattern) . ', ' . $valueExpression . ') === 1';
    }

    public function message(): string
    {
        return 'Value must match the configured pattern';
    }
}
