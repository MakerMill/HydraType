<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Compiler;

use LogicException;

/** @internal Formatting helper for generated hydrator source. */
final class PhpCodeBuilder
{
    /** @var list<string> */
    private array $lines = [];
    private int $indentation = 0;

    public function line(string $line = ''): void
    {
        $this->lines[] = $line === '' ? '' : str_repeat(' ', $this->indentation * 4) . $line;
    }

    public function open(string $declaration): void
    {
        $this->line($declaration);
        $this->line('{');
        $this->indent();
    }

    public function openInline(string $declaration): void
    {
        $this->line($declaration . ' {');
        $this->indent();
    }

    public function close(string $suffix = ''): void
    {
        $this->outdent();
        $this->line('}' . $suffix);
    }

    public function indent(): void
    {
        $this->indentation++;
    }

    public function outdent(): void
    {
        if ($this->indentation === 0) {
            throw new LogicException('Generated PHP indentation cannot be negative.');
        }

        $this->indentation--;
    }

    public function code(string $code): void
    {
        foreach (explode("\n", $code) as $line) {
            $this->line($line);
        }
    }

    public function build(): string
    {
        if ($this->indentation !== 0) {
            throw new LogicException('Generated PHP contains an unclosed block.');
        }

        return implode("\n", $this->lines) . "\n";
    }
}
