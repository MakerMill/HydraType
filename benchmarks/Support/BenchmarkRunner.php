<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Support;

use InvalidArgumentException;

final class BenchmarkRunner
{
    /**
     * @param non-empty-array<string, BenchmarkCase> $cases
     *
     * @return array<string, non-empty-list<float>> Nanoseconds per operation.
     */
    public static function measure(array $cases, int $samples): array
    {
        if ($samples < 1) {
            throw new InvalidArgumentException('A benchmark must collect at least one sample.');
        }

        foreach ($cases as $case) {
            $warmup = $case->warmup ?? $case->operation;
            ($case->verify)($warmup());
        }

        /** @var array<string, list<float>> $results */
        $results = array_fill_keys(array_keys($cases), []);
        $names = array_keys($cases);

        for ($sample = 0; $sample < $samples; $sample++) {
            // Vary execution order so a consistently first or last case does not inherit that position's bias.
            shuffle($names);
            foreach ($names as $name) {
                $case = $cases[$name];
                if ($case->prepare !== null) {
                    ($case->prepare)();
                }
                $start = hrtime(true);
                $result = ($case->operation)();
                $elapsed = hrtime(true) - $start;

                ($case->verify)($result);
                $results[$name][] = $elapsed / $case->operations;
            }
        }

        /** @var array<string, non-empty-list<float>> $results */
        return $results;
    }
}
