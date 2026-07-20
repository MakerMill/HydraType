<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Contains implements AssertionInterface
{
    public function __construct(private string $fragment)
    {
        if ($fragment === '') {
            throw new InvalidArgumentException('Contains fragment must not be empty.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return 'str_contains(' . $valueExpression . ', ' . PhpLiteral::string($this->fragment) . ')';
    }

    public function message(): string
    {
        return 'Value must contain the configured fragment';
    }
}
