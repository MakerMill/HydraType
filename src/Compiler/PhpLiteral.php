<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler;

/**
 * Emits configured scalar values as safe PHP source for custom compile-time extensions.
 *
 * This is a supported extension helper because mutators and assertions return generated PHP expressions.
 */
final class PhpLiteral
{
    public static function value(string|int|float|bool $value): string
    {
        return is_string($value) ? self::string($value) : var_export($value, true);
    }

    public static function string(string $value): string
    {
        // Emit readable escapes while preventing interpolation or control bytes from changing generated PHP.
        $literal = '"';
        for ($index = 0; $index < strlen($value); $index++) {
            $character = $value[$index];
            $literal .= match (ord($character)) {
                0 => '\\x00',
                9 => '\\t',
                10 => '\\n',
                11 => '\\v',
                12 => '\\f',
                13 => '\\r',
                27 => '\\e',
                34 => '\\"',
                36 => '\\$',
                92 => '\\\\',
                127 => '\\x7F',
                default => ord($character) < 32
                    ? sprintf('\\x%02X', ord($character))
                    : $character,
            };
        }

        return $literal . '"';
    }
}
