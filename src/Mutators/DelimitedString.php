<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DelimitedString implements MutatorInterface, ExtractionMutatorInterface
{
    public function __construct(private string $separator)
    {
        if ($separator === '') {
            throw new InvalidArgumentException('DelimitedString separator must not be empty.');
        }
    }

    public function compile(string $inputExpression): string
    {
        return 'explode(' . PhpLiteral::string($this->separator) . ", (string) {$inputExpression})";
    }

    public function outputType(): string
    {
        return Type::Array->value;
    }

    public function compileExtraction(string $inputExpression): string
    {
        return 'implode(' . PhpLiteral::string($this->separator) . ", {$inputExpression})";
    }
}
