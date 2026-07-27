<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer\Mutators;

use Attribute;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class InvalidPhp implements MutatorInterface
{
    public function compile(string $inputExpression): string
    {
        return '(';
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
