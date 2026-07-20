<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class RegexReplace implements MutatorInterface
{
    public function __construct(private string $pattern, private string $replacement)
    {
        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING);
        try {
            $isValid = preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$isValid) {
            throw new InvalidArgumentException('RegexReplace pattern must be a valid PCRE pattern.');
        }
    }

    public function compile(string $inputExpression): string
    {
        return '(preg_replace('
            . PhpLiteral::string($this->pattern)
            . ', '
            . PhpLiteral::string($this->replacement)
            . ", (string) {$inputExpression})"
            . " ?? throw new \\RuntimeException('Regex replacement failed.'))";
    }

    public function outputType(): string
    {
        return Type::String->value;
    }
}
