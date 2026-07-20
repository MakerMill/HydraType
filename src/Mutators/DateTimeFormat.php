<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use DateTimeImmutable;
use DateTimeInterface;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateTimeFormat implements MutatorInterface, ExtractionMutatorInterface
{
    public function __construct(private string $format = DateTimeInterface::ATOM)
    {
    }

    public function compile(string $inputExpression): string
    {
        return 'new \\DateTimeImmutable((string) ' . $inputExpression . ')';
    }

    public function outputType(): string
    {
        return DateTimeImmutable::class;
    }

    public function compileExtraction(string $inputExpression): string
    {
        return $inputExpression . '->format(' . PhpLiteral::string($this->format) . ')';
    }
}
