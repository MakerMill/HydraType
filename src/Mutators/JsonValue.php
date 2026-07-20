<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class JsonValue implements MutatorInterface, ExtractionMutatorInterface
{
    public function __construct(
        private bool $associative = true,
        private int $depth = 512,
        private int $decodeFlags = 0,
        private int $encodeFlags = 0,
    ) {
        if ($this->depth < 1 || $this->depth > 2_147_483_647) {
            throw new InvalidArgumentException('JSON depth must be between 1 and 2147483647.');
        }
    }

    public function compile(string $inputExpression): string
    {
        $associative = $this->associative ? 'true' : 'false';
        $flags = $this->compileFlags($this->decodeFlags);

        return "json_decode((string) {$inputExpression}, {$associative}, {$this->depth}, {$flags})";
    }

    public function outputType(): string
    {
        return $this->associative ? Type::Array->value : Type::Object->value;
    }

    public function compileExtraction(string $inputExpression): string
    {
        $flags = $this->compileFlags($this->encodeFlags);

        return "json_encode({$inputExpression}, {$flags}, {$this->depth})";
    }

    private function compileFlags(int $configuredFlags): string
    {
        // Decoding and encoding failures must remain visible even when callers provide their own JSON flags.
        $additionalFlags = $configuredFlags & ~JSON_THROW_ON_ERROR;
        $flags = '\\JSON_THROW_ON_ERROR';
        if ($additionalFlags !== 0) {
            $flags .= " | {$additionalFlags}";
        }

        return $flags;
    }
}
