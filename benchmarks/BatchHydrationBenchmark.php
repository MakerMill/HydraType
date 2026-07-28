<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\CompetitorRecord;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydraType;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param list<array{id: int, userName: string, email: string, city: string, active: bool}> $dataSet
 *
 * @return list<CompetitorRecord>
 */
function hydrateRowsIndividually(HydraType $hydra, array $dataSet, int $repetitions): array
{
    $objects = [];
    for ($repetition = 0; $repetition < $repetitions; $repetition++) {
        $objects = [];
        foreach ($dataSet as $data) {
            $objects[] = $hydra->hydrate(CompetitorRecord::class, $data);
        }
    }

    return $objects;
}

/**
 * @param list<array{id: int, userName: string, email: string, city: string, active: bool}> $dataSet
 *
 * @return array<int, CompetitorRecord>
 */
function hydrateRowsAsBatch(HydraType $hydra, array $dataSet, int $repetitions): array
{
    $objects = [];
    for ($repetition = 0; $repetition < $repetitions; $repetition++) {
        $objects = $hydra->hydrateMany(CompetitorRecord::class, $dataSet);
    }

    return $objects;
}

/**
 * @param list<array{id: int, userName: string, email: string, city: string, active: bool}> $dataSet
 *
 * @return list<CompetitorRecord>
 */
function hydrateRowsByHand(array $dataSet, int $repetitions): array
{
    $objects = [];
    for ($repetition = 0; $repetition < $repetitions; $repetition++) {
        $objects = [];
        foreach ($dataSet as $data) {
            $objects[] = new CompetitorRecord(
                (int) $data['id'],
                (string) $data['userName'],
                (string) $data['email'],
                (string) $data['city'],
                (bool) $data['active'],
            );
        }
    }

    return $objects;
}

/** @return Closure(mixed): void */
function batchHydrationVerifier(int $expectedCount, int $expectedChecksum): Closure
{
    return static function (mixed $result) use ($expectedCount, $expectedChecksum): void {
        if (
            !is_array($result)
            || count($result) !== $expectedCount
            || !$result[0] instanceof CompetitorRecord
            || $result[0]->checksum() !== $expectedChecksum
        ) {
            throw new RuntimeException('Batch hydration produced invalid objects.');
        }
    };
}

$options = getopt('', ['objects::', 'batch::', 'samples::']);
$requestedObjects = Options::integer($options, 'objects', 500_000);
$batchSize = Options::integer($options, 'batch', 1_000);
$samples = Options::integer($options, 'samples', 9, 3);
$repetitions = max(1, intdiv($requestedObjects, $batchSize));
$objectsPerSample = $repetitions * $batchSize;

$data = [
    'id' => 42,
    'userName' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'city' => 'London',
    'active' => true,
];
$expectedChecksum = (new CompetitorRecord(...$data))->checksum();
$dataSet = array_fill(0, $batchSize, $data);
$configuration = new Configuration(
    hydratorDirectory: __DIR__ . '/../hydrators/batch-hydration-benchmark',
);
$hydra = new HydraType($configuration);
$hydra->hydrator(CompetitorRecord::class);
$verify = batchHydrationVerifier($batchSize, $expectedChecksum);
$warmupRepetitions = 1;
$prepare = static function (): void {
    gc_collect_cycles();
};

$cases = [
    'Handwritten PHP' => new BenchmarkCase(
        static fn (): array => hydrateRowsByHand($dataSet, $repetitions),
        $objectsPerSample,
        $verify,
        static fn (): array => hydrateRowsByHand($dataSet, $warmupRepetitions),
        $prepare,
    ),
    'HydraType hydrateMany()' => new BenchmarkCase(
        static fn (): array => hydrateRowsAsBatch($hydra, $dataSet, $repetitions),
        $objectsPerSample,
        $verify,
        static fn (): array => hydrateRowsAsBatch($hydra, $dataSet, $warmupRepetitions),
        $prepare,
    ),
    'Repeated HydraType hydrate()' => new BenchmarkCase(
        static fn (): array => hydrateRowsIndividually($hydra, $dataSet, $repetitions),
        $objectsPerSample,
        $verify,
        static fn (): array => hydrateRowsIndividually($hydra, $dataSet, $warmupRepetitions),
        $prepare,
    ),
];
$results = BenchmarkRunner::measure($cases, $samples);
$medians = [];
foreach ($results as $name => $values) {
    $medians[$name] = Statistics::median($values);
}

$handwritten = $medians['Handwritten PHP'];
$hydrateMany = $medians['HydraType hydrateMany()'];
$hydrateRepeatedly = $medians['Repeated HydraType hydrate()'];

printf("Batch hydration benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%d objects per sample, %d samples, batches of %d\n\n",
    $objectsPerSample,
    $samples,
    $batchSize,
);
printf("%-30s %14s %14s\n", 'Method', 'median ns', 'relative');
printf("%s\n", str_repeat('-', 60));
foreach ($medians as $name => $median) {
    printf("%-30s %14.1f %13.2fx\n", $name, $median, $median / $handwritten);
}
printf(
    "\nhydrateMany() vs repeated hydrate(): %.1f%% faster\n",
    (1 - $hydrateMany / $hydrateRepeatedly) * 100,
);
printf(
    "hydrateMany() vs handwritten PHP: %.1f%% slower\n",
    ($hydrateMany / $handwritten - 1) * 100,
);
