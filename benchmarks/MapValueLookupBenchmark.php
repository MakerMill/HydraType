<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param Closure(list<string>, int): int $operation
 * @param list<string>                    $inputs
 *
 * @return array{median: float, minimum: float, maximum: float}
 */
function measureMapValue(Closure $operation, array $inputs, int $iterations, int $samples): array
{
    $measurements = [];
    $expected = $operation($inputs, count($inputs));
    $operation($inputs, min($iterations, 50_000));

    for ($sample = 0; $sample < $samples; $sample++) {
        $start = hrtime(true);
        $checksum = $operation($inputs, $iterations);
        $measurements[] = (hrtime(true) - $start) / $iterations;

        if ($checksum !== $expected * intdiv($iterations, count($inputs))) {
            throw new RuntimeException('Lookup benchmark produced an unexpected checksum.');
        }
    }

    return [
        'median' => Statistics::median($measurements),
        'minimum' => min($measurements),
        'maximum' => max($measurements),
    ];
}

/** @return Closure(list<string>, int): int */
function compileMapValueOperation(int $size, bool $useMatch): Closure
{
    $entries = [];
    for ($index = 0; $index < $size; $index++) {
        $entries[] = var_export('key-' . $index, true) . ' => ' . var_export('value-' . $index, true);
    }
    $map = '[' . implode(', ', $entries) . ']';
    $lookup = $useMatch
        ? 'match ($value) {' . implode(', ', $entries) . ', default => $value}'
        : '((is_int($mapValue = $value) || is_string($mapValue))'
            . ' ? (' . $map . '[$mapValue] ?? $mapValue) : $mapValue)';

    $operation = eval(
        'return static function (array $inputs, int $iterations): int {'
        . '$checksum = 0;'
        . '$inputCount = count($inputs);'
        . 'for ($index = 0; $index < $iterations; $index++) {'
        . '$value = $inputs[$index % $inputCount];'
        . '$mapped = ' . $lookup . ';'
        . '$checksum += strlen($mapped);'
        . '}'
        . 'return $checksum;'
        . '};'
    );
    if (!$operation instanceof Closure) {
        throw new RuntimeException('Unable to compile lookup benchmark operation.');
    }

    return $operation;
}

$iterations = (int) ($_SERVER['argv'][1] ?? 500_000);
$samples = (int) ($_SERVER['argv'][2] ?? 9);
if ($iterations < 4 || $iterations % 4 !== 0 || $samples < 1) {
    throw new InvalidArgumentException('Iterations must be a positive multiple of four and samples must be positive.');
}

printf("MapValue lookup benchmark (PHP %s)\n", PHP_VERSION);
printf("%d lookups per sample, %d samples\n\n", $iterations, $samples);
printf("%6s  %-7s %12s %12s %12s\n", 'Size', 'Method', 'median ns', 'min ns', 'max ns');
printf("%s\n", str_repeat('-', 59));

foreach ([2, 4, 8, 16, 32, 64] as $size) {
    $inputs = ['key-0', 'key-' . intdiv($size, 2), 'missing', 'key-' . ($size - 1)];
    foreach (['match' => true, 'array' => false] as $method => $useMatch) {
        $result = measureMapValue(
            compileMapValueOperation($size, $useMatch),
            $inputs,
            $iterations,
            $samples,
        );
        printf(
            "%6d  %-7s %12.2f %12.2f %12.2f\n",
            $size,
            $method,
            $result['median'],
            $result['minimum'],
            $result['maximum'],
        );
    }
}
