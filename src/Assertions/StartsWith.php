<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class StartsWith implements AssertionInterface
{
    public function __construct(private string $prefix)
    {
        if ($prefix === '') {
            throw new InvalidArgumentException('StartsWith prefix must not be empty.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return 'str_starts_with(' . $valueExpression . ', ' . PhpLiteral::string($this->prefix) . ')';
    }

    public function message(): string
    {
        return 'Value must start with the configured prefix';
    }
}
