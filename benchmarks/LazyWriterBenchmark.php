<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\EagerWriterHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\InlineLazyWriterHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\LazyWriterHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\WriterLifecycleHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\WriterLifecycleTarget;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param array<string, Closure(int): int> $cases
 *
 * @return array<string, array<int, float>>
 */
function benchmarkWriterCases(array $cases, int $operations, int $warmup, int $samples): array
{
    $benchmarkCases = [];
    foreach ($cases as $name => $case) {
        $benchmarkCases[$name] = new BenchmarkCase(
            static fn (): int => $case($operations),
            $operations,
            static function (mixed $completed) use ($name, $operations): void {
                if ($completed !== $operations) {
                    throw new RuntimeException(sprintf('%s completed %d operations', $name, $completed));
                }
            },
            static function () use ($case, $warmup, $operations): int {
                $case($warmup);

                return $operations;
            },
        );
    }

    return BenchmarkRunner::measure($benchmarkCases, $samples);
}

/**
 * @param array<string, array<int, float>> $results
 */
function printWriterResults(array $results): void
{
    $eager = Statistics::median($results['eager']);

    foreach ($results as $name => $samples) {
        if ($samples === []) {
            throw new RuntimeException(sprintf('%s has no benchmark samples', $name));
        }

        $median = Statistics::median($samples);
        $relative = $name === 'eager' ? '' : sprintf('  %+.2f%%', (($median / $eager) - 1) * 100);
        printf(
            "  %-12s %8.2f ns/op median (%8.2f min, %8.2f p95)%s\n",
            $name . ':',
            $median,
            min($samples),
            Statistics::percentile($samples, 0.95),
            $relative
        );
    }
}

/**
 * @param class-string<WriterLifecycleHydrator> $hydratorClass
 * @param array<string, mixed>                   $data
 */
function constructAndHydrate(string $hydratorClass, array $data, int $count): int
{
    $object = null;
    for ($i = 0; $i < $count; $i++) {
        $object = (new $hydratorClass())->hydrate($data);
    }

    if (!$object instanceof WriterLifecycleTarget || $object->checksum() !== 20) {
        throw new RuntimeException('Hydration produced an invalid object');
    }

    return $count;
}

/**
 * @param class-string<WriterLifecycleHydrator> $hydratorClass
 */
function constructHydrators(string $hydratorClass, int $count): int
{
    $hydrator = null;
    for ($i = 0; $i < $count; $i++) {
        $hydrator = new $hydratorClass();
    }

    if (!$hydrator instanceof WriterLifecycleHydrator) {
        throw new RuntimeException('Construction produced an invalid hydrator');
    }

    return $count;
}

/**
 * @param class-string<WriterLifecycleHydrator> $hydratorClass
 * @param array<string, mixed>                   $data
 */
function hydrateRepeatedly(string $hydratorClass, array $data, int $count): int
{
    $hydrator = new $hydratorClass();
    $hydrator->hydrate($data);
    $object = null;
    for ($i = 0; $i < $count; $i++) {
        $object = $hydrator->hydrate($data);
    }

    if (!$object instanceof WriterLifecycleTarget || $object->checksum() !== 20) {
        throw new RuntimeException('Repeated hydration produced an invalid object');
    }

    return $count;
}

/**
 * @param class-string<WriterLifecycleHydrator> $hydratorClass
 * @param array<string, mixed>                   $camelData
 * @param array<string, mixed>                   $snakeData
 */
function hydrateAlternating(
    string $hydratorClass,
    array $camelData,
    array $snakeData,
    int $count
): int {
    $hydrator = new $hydratorClass();
    $hydrator->hydrate($camelData);
    $hydrator->hydrate($snakeData);
    $object = null;
    for ($i = 0; $i < $count; $i++) {
        $object = $hydrator->hydrate(($i & 1) === 0 ? $camelData : $snakeData);
    }

    if (!$object instanceof WriterLifecycleTarget || $object->checksum() !== 20) {
        throw new RuntimeException('Alternating hydration produced an invalid object');
    }

    return $count;
}

/**
 * @param class-string<WriterLifecycleHydrator> $hydratorClass
 * @param array<string, mixed>                   $data
 */
function hydrateInBatches(string $hydratorClass, array $data, int $count, int $batchSize): int
{
    $hydrator = new $hydratorClass();
    $dataSet = array_fill(0, $batchSize, $data);
    $completed = 0;
    $object = null;

    while ($completed < $count) {
        $currentSize = min($batchSize, $count - $completed);
        $objects = $hydrator->hydrateMany(
            $currentSize === $batchSize ? $dataSet : array_slice($dataSet, 0, $currentSize)
        );
        $object = $objects[count($objects) - 1];
        $completed += $currentSize;
    }

    if (!$object instanceof WriterLifecycleTarget || $object->checksum() !== 20) {
        throw new RuntimeException('Batch hydration produced an invalid object');
    }

    return $completed;
}

$options = getopt('', ['operations::', 'warmup::', 'samples::']);
$operations = Options::integer($options, 'operations', 100_000, 1_000);
$warmup = Options::integer($options, 'warmup', 10_000, 100);
$samples = Options::integer($options, 'samples', 15, 3);

$camelData = [
    'id' => 1,
    'userName' => 'John Doe',
    'password' => 'secret',
    'type' => 'USER',
    'active' => true,
];
$snakeData = [
    'id' => 1,
    'user_name' => 'John Doe',
    'password' => 'secret',
    'type' => 'USER',
    'active' => true,
];

$scenarios = [
    'construct only' => [
        'eager' => static fn (int $count): int => constructHydrators(EagerWriterHydrator::class, $count),
        'accessor lazy' => static fn (int $count): int => constructHydrators(LazyWriterHydrator::class, $count),
        'inline lazy' => static fn (int $count): int => constructHydrators(
            InlineLazyWriterHydrator::class,
            $count
        ),
    ],
    'construct + first camel hydration' => [
        'eager' => static fn (int $count): int => constructAndHydrate(
            EagerWriterHydrator::class,
            $camelData,
            $count
        ),
        'accessor lazy' => static fn (int $count): int => constructAndHydrate(
            LazyWriterHydrator::class,
            $camelData,
            $count
        ),
        'inline lazy' => static fn (int $count): int => constructAndHydrate(
            InlineLazyWriterHydrator::class,
            $camelData,
            $count
        ),
    ],
    'construct + first snake hydration' => [
        'eager' => static fn (int $count): int => constructAndHydrate(
            EagerWriterHydrator::class,
            $snakeData,
            $count
        ),
        'accessor lazy' => static fn (int $count): int => constructAndHydrate(
            LazyWriterHydrator::class,
            $snakeData,
            $count
        ),
        'inline lazy' => static fn (int $count): int => constructAndHydrate(
            InlineLazyWriterHydrator::class,
            $snakeData,
            $count
        ),
    ],
    'cached camel hydrate()' => [
        'eager' => static fn (int $count): int => hydrateRepeatedly(
            EagerWriterHydrator::class,
            $camelData,
            $count
        ),
        'accessor lazy' => static fn (int $count): int => hydrateRepeatedly(
            LazyWriterHydrator::class,
            $camelData,
            $count
        ),
        'inline lazy' => static fn (int $count): int => hydrateRepeatedly(
            InlineLazyWriterHydrator::class,
            $camelData,
            $count
        ),
    ],
    'cached alternating camel/snake hydrate()' => [
        'eager' => static fn (int $count): int => hydrateAlternating(
            EagerWriterHydrator::class,
            $camelData,
            $snakeData,
            $count
        ),
        'accessor lazy' => static fn (int $count): int => hydrateAlternating(
            LazyWriterHydrator::class,
            $camelData,
            $snakeData,
            $count
        ),
        'inline lazy' => static fn (int $count): int => hydrateAlternating(
            InlineLazyWriterHydrator::class,
            $camelData,
            $snakeData,
            $count
        ),
    ],
];

foreach ([1, 10, 1_000] as $batchSize) {
    $scenarios[sprintf('cached camel hydrateMany(), batch %d', $batchSize)] = [
        'eager' => static fn (int $count): int => hydrateInBatches(
            EagerWriterHydrator::class,
            $camelData,
            $count,
            $batchSize
        ),
        'accessor lazy' => static fn (int $count): int => hydrateInBatches(
            LazyWriterHydrator::class,
            $camelData,
            $count,
            $batchSize
        ),
        'inline lazy' => static fn (int $count): int => hydrateInBatches(
            InlineLazyWriterHydrator::class,
            $camelData,
            $count,
            $batchSize
        ),
    ];
}

printf("Lazy writer benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s operations/sample | %s warm-up operations | %d samples\n\n",
    number_format($operations),
    number_format($warmup),
    $samples
);

mt_srand(20260718);
foreach ($scenarios as $name => $cases) {
    printf("%s\n", $name);
    printWriterResults(benchmarkWriterCases($cases, $operations, $warmup, $samples));
    printf("\n");
}

printf("Percentages are relative to eager; positive is slower and negative is faster.\n");
