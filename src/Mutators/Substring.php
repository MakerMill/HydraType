<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Substring implements MutatorInterface
{
    public function __construct(private int $offset, private ?int $length = null)
    {
    }

    public function compile(string $inputExpression): string
    {
        $arguments = "(string) {$inputExpression}, {$this->offset}";
        if ($this->length !== null) {
            $arguments .= ", {$this->length}";
        }

        return "substr({$arguments})";
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
