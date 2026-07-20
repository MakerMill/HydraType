<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class StringReplace implements MutatorInterface
{
    public function __construct(private string $search, private string $replacement)
    {
        if ($this->search === '') {
            throw new InvalidArgumentException('String replacement search must not be empty.');
        }
    }

    public function compile(string $inputExpression): string
    {
        return 'str_replace('
            . PhpLiteral::string($this->search)
            . ', '
            . PhpLiteral::string($this->replacement)
            . ", (string) {$inputExpression})";
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
