<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class EndsWith implements AssertionInterface
{
    public function __construct(private string $suffix)
    {
        if ($suffix === '') {
            throw new InvalidArgumentException('EndsWith suffix must not be empty.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return 'str_ends_with(' . $valueExpression . ', ' . PhpLiteral::string($this->suffix) . ')';
    }

    public function message(): string
    {
        return 'Value must end with the configured suffix';
    }
}
