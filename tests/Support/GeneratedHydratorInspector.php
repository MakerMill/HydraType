<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Support;

use RuntimeException;

final class GeneratedHydratorInspector
{
    public static function closureBody(string $source, string $methodName): string
    {
        $tokens = self::tokens($source);
        [$methodOpen, $methodClose] = self::methodRange($tokens, $methodName);

        for ($index = $methodOpen + 1; $index < $methodClose; $index++) {
            if (!self::isToken($tokens[$index], T_FUNCTION)) {
                continue;
            }

            $next = self::nextMeaningfulToken($tokens, $index + 1);
            if ($next === null || self::text($tokens[$next]) !== '(') {
                continue;
            }

            $closureOpen = self::nextTokenText($tokens, $next + 1, '{', $methodClose);
            if ($closureOpen === null) {
                break;
            }

            return self::body($tokens, $closureOpen, self::matchingBrace($tokens, $closureOpen));
        }

        throw new RuntimeException("Generated hydrator method '{$methodName}' does not contain a closure.");
    }

    public static function methodBody(string $source, string $methodName): string
    {
        $tokens = self::tokens($source);
        [$open, $close] = self::methodRange($tokens, $methodName);

        return self::body($tokens, $open, $close);
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{int, int}
     */
    private static function methodRange(array $tokens, string $methodName): array
    {
        foreach ($tokens as $index => $token) {
            if (!self::isToken($token, T_FUNCTION)) {
                continue;
            }

            $nameIndex = self::nextMeaningfulToken($tokens, $index + 1);
            if ($nameIndex === null || !self::isToken($tokens[$nameIndex], T_STRING)) {
                continue;
            }
            if (self::text($tokens[$nameIndex]) !== $methodName) {
                continue;
            }

            $open = self::nextTokenText($tokens, $nameIndex + 1, '{');
            if ($open === null) {
                break;
            }

            return [$open, self::matchingBrace($tokens, $open)];
        }

        throw new RuntimeException("Generated hydrator method '{$methodName}' was not found.");
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function matchingBrace(array $tokens, int $open): int
    {
        $depth = 0;
        for ($index = $open; $index < count($tokens); $index++) {
            $text = self::text($tokens[$index]);
            if ($text === '{') {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        throw new RuntimeException('Generated hydrator contains an unclosed block.');
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function body(array $tokens, int $open, int $close): string
    {
        $body = '';
        for ($index = $open + 1; $index < $close; $index++) {
            $body .= self::text($tokens[$index]);
        }

        $lines = explode("\n", $body);
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }
        $indentation = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            preg_match('/^ */', $line, $matches);
            $leadingSpaces = strlen($matches[0] ?? '');
            $indentation = $indentation === null ? $leadingSpaces : min($indentation, $leadingSpaces);
        }

        if ($indentation === null || $indentation === 0) {
            return implode("\n", $lines);
        }

        return implode("\n", array_map(
            static fn (string $line): string => substr($line, $indentation),
            $lines,
        ));
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function nextMeaningfulToken(array $tokens, int $start): ?int
    {
        for ($index = $start; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (
                self::isToken($token, T_WHITESPACE)
                || self::isToken($token, T_COMMENT)
                || self::isToken($token, T_DOC_COMMENT)
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function nextTokenText(array $tokens, int $start, string $expected, ?int $end = null): ?int
    {
        $end ??= count($tokens);
        for ($index = $start; $index < $end; $index++) {
            if (self::text($tokens[$index]) === $expected) {
                return $index;
            }
        }

        return null;
    }

    /** @param array{int, string, int}|string $token */
    private static function isToken(array|string $token, int $type): bool
    {
        return is_array($token) && $token[0] === $type;
    }

    /** @param array{int, string, int}|string $token */
    private static function text(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    /** @return list<array{int, string, int}|string> */
    private static function tokens(string $source): array
    {
        return token_get_all($source, TOKEN_PARSE);
    }
}
