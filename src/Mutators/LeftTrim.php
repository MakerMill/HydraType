<?php

declare(strict_types = 1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class LeftTrim implements MutatorInterface
{
    public function __construct(private string $characters = " \t\n\r\0\x0B")
    {
        if ($characters === '') {
            throw new InvalidArgumentException('LeftTrim character list must not be empty.');
        }
    }

    public function compile(string $inputExpression): string
    {
        return 'ltrim((string) ' . $inputExpression . ', ' . PhpLiteral::string($this->characters) . ')';
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
