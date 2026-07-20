<?php

declare(strict_types=1);

use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

it('calculates shared benchmark statistics', function (): void {
    expect(Statistics::median([4.0, 1.0, 3.0, 2.0]))->toBe(2.5)
        ->and(Statistics::percentile([4.0, 1.0, 3.0, 2.0], 0.95))->toBe(4.0);
});

it('normalizes benchmark command options', function (): void {
    $options = ['iterations' => '20', 'sizes' => '1,5,5,-1'];

    expect(Options::integer($options, 'iterations', 10))->toBe(20)
        ->and(Options::integer($options, 'samples', 9, 3))->toBe(9)
        ->and(Options::integerList($options, 'sizes', [2]))->toBe([1, 5]);
});

it('keeps preparation warmup and verification outside benchmark operations', function (): void {
    $operations = 0;
    $warmups = 0;
    $preparations = 0;
    $verifications = 0;

    $case = new BenchmarkCase(
        static function () use (&$operations): int {
            $operations++;

            return 42;
        },
        2,
        static function (mixed $result) use (&$verifications): void {
            expect($result)->toBe(42);
            $verifications++;
        },
        static function () use (&$warmups): int {
            $warmups++;

            return 42;
        },
        static function () use (&$preparations): void {
            $preparations++;
        },
    );

    $results = BenchmarkRunner::measure(['case' => $case], 3);

    expect($operations)->toBe(3)
        ->and($warmups)->toBe(1)
        ->and($preparations)->toBe(3)
        ->and($verifications)->toBe(4)
        ->and($results['case'])->toHaveCount(3);
});
