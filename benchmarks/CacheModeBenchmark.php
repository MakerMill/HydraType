<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\CompetitorRecord;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\Configuration;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param Closure(): mixed $operation
 *
 * @return array{median: float, minimum: float, maximum: float}
 */
function measure(Closure $operation, int $iterations, int $samples): array
{
    $values = [];
    for ($sample = 0; $sample < $samples; $sample++) {
        $start = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $operation();
        }
        $values[] = (hrtime(true) - $start) / $iterations;
    }

    return [
        'median' => Statistics::median($values),
        'minimum' => min($values),
        'maximum' => max($values),
    ];
}

$namespace = 'MakerMill\\HydraType\\Benchmarks\\Generated\\CacheMode';
$directory = __DIR__ . '/../hydrators/cache-mode-benchmark';
$automaticConfiguration = new Configuration($namespace, $directory, CacheMode::Auto);
$automaticConfiguration->getHydratorFactory()->create(CompetitorRecord::class);

$automaticResolution = static fn (): object => (new Configuration(
    $namespace,
    $directory,
    CacheMode::Auto,
))->getHydratorFactory()->create(CompetitorRecord::class);
$readOnlyResolution = static fn (): object => (new Configuration(
    $namespace,
    $directory,
    CacheMode::ReadOnly,
))->getHydratorFactory()->create(CompetitorRecord::class);
$factory = $automaticConfiguration->getHydratorFactory();
$inMemoryResolution = static fn (): object => $factory->create(CompetitorRecord::class);

$iterations = (int) ($_SERVER['argv'][1] ?? 10_000);
$samples = (int) ($_SERVER['argv'][2] ?? 9);
if ($iterations < 1 || $samples < 1) {
    throw new InvalidArgumentException('Iterations and samples must both be positive integers.');
}

$cases = [
    'Auto first resolution' => $automaticResolution,
    'Read-only first resolution' => $readOnlyResolution,
    'In-memory resolution' => $inMemoryResolution,
];

printf("Cache mode benchmark (PHP %s)\n", PHP_VERSION);
printf("%d resolutions per sample, %d samples\n\n", $iterations, $samples);
printf("%-28s %12s %12s %12s\n", 'Path', 'median ns', 'min ns', 'max ns');
printf("%s\n", str_repeat('-', 70));

foreach ($cases as $name => $case) {
    $result = measure($case, $iterations, $samples);
    printf(
        "%-28s %12.1f %12.1f %12.1f\n",
        $name,
        $result['median'],
        $result['minimum'],
        $result['maximum'],
    );
}
