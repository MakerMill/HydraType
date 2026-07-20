<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Support;

use InvalidArgumentException;

final class Statistics
{
    /** @param non-empty-list<float|int> $values */
    public static function percentile(array $values, float $percentile): float
    {
        if ($percentile < 0.0 || $percentile > 1.0) {
            throw new InvalidArgumentException('Percentile must be between zero and one.');
        }

        sort($values, SORT_NUMERIC);
        $index = (int) ceil($percentile * count($values)) - 1;

        return (float) $values[max(0, min($index, count($values) - 1))];
    }

    /** @param non-empty-list<float|int> $values */
    public static function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? ((float) $values[$middle - 1] + (float) $values[$middle]) / 2
            : (float) $values[$middle];
    }
}
