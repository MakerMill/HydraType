<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer\Mutators;

use Attribute;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Base64Value implements MutatorInterface, ExtractionMutatorInterface
{
    public function compile(string $inputExpression): string
    {
        return 'base64_decode((string) ' . $inputExpression . ', true)';
    }

    public function outputType(): string
    {
        return Type::String->value;
    }

    public function compileExtraction(string $inputExpression): string
    {
        return 'base64_encode((string) ' . $inputExpression . ')';
    }
}
