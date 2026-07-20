<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration\BenchmarkProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration\FlatProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration\OneLevelProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\NestedHydration\TwoLevelProfile;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\Interfaces\HydratorInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @param array<string, list<float>> $results */
function printNestedHydrationResults(string $title, array $results): void
{
    $flatMedian = Statistics::median($results['flat']);

    printf("\n%s\n", $title);
    printf("%-12s %12s %12s %12s %12s\n", 'Shape', 'median ns', 'p95 ns', 'vs flat', 'objects M/s');
    printf("%s\n", str_repeat('-', 66));
    foreach ($results as $name => $values) {
        $median = Statistics::median($values);
        printf(
            "%-12s %12.2f %12.2f %11.2fx %12.2f\n",
            $name,
            $median,
            Statistics::percentile($values, 0.95),
            $median / $flatMedian,
            1_000 / $median,
        );
    }
}

/** @return Closure(mixed): void */
function nestedProfileVerifier(int $expectedChecksum): Closure
{
    return static function (mixed $result) use ($expectedChecksum): void {
        if (!$result instanceof BenchmarkProfile || $result->checksum() !== $expectedChecksum) {
            throw new RuntimeException('Nested hydration produced an invalid profile.');
        }
    };
}

/** @param array<string, mixed> $expected */
function nestedExtractionVerifier(array $expected): Closure
{
    return static function (mixed $result) use ($expected): void {
        if ($result !== $expected) {
            throw new RuntimeException('Nested extraction produced invalid data.');
        }
    };
}

/**
 * @param array<string, mixed> $data
 *
 * @return Closure(): object
 */
function nestedSingleHydration(HydratorInterface $hydrator, array $data, int $iterations): Closure
{
    if ($iterations < 1) {
        throw new InvalidArgumentException('Single hydration iterations must be positive.');
    }

    return static function () use ($hydrator, $data, $iterations): object {
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $object = $hydrator->hydrate($data);
        }

        return $object;
    };
}

/** @return Closure(): array<string, mixed> */
function nestedSingleExtraction(HydratorInterface $hydrator, object $object, int $iterations): Closure
{
    if ($iterations < 1) {
        throw new InvalidArgumentException('Single extraction iterations must be positive.');
    }

    return static function () use ($hydrator, $object, $iterations): array {
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $data = $hydrator->extract($object);
        }

        return $data;
    };
}

/**
 * @param non-empty-list<array<string, mixed>> $dataSet
 *
 * @return Closure(): object
 */
function nestedBatchHydration(HydratorInterface $hydrator, array $dataSet, int $repetitions): Closure
{
    if ($repetitions < 1) {
        throw new InvalidArgumentException('Batch hydration repetitions must be positive.');
    }

    return static function () use ($hydrator, $dataSet, $repetitions): object {
        for ($repetition = 0; $repetition < $repetitions; $repetition++) {
            $objects = $hydrator->hydrateMany($dataSet);
        }

        $lastKey = array_key_last($objects);
        if ($lastKey === null) {
            throw new RuntimeException('Nested batch hydration returned no objects.');
        }

        return $objects[$lastKey];
    };
}

/**
 * @param non-empty-list<object> $objects
 *
 * @return Closure(): array<string, mixed>
 */
function nestedBatchExtraction(HydratorInterface $hydrator, array $objects, int $repetitions): Closure
{
    if ($repetitions < 1) {
        throw new InvalidArgumentException('Batch extraction repetitions must be positive.');
    }

    return static function () use ($hydrator, $objects, $repetitions): array {
        for ($repetition = 0; $repetition < $repetitions; $repetition++) {
            $dataSet = $hydrator->extractMany($objects);
        }

        $lastKey = array_key_last($dataSet);
        if ($lastKey === null) {
            throw new RuntimeException('Nested batch extraction returned no rows.');
        }

        return $dataSet[$lastKey];
    };
}

$options = getopt('', ['objects::', 'batch::', 'samples::']);
$iterations = Options::integer($options, 'objects', 100_000);
$batchSize = Options::integer($options, 'batch', 1_000);
$samples = Options::integer($options, 'samples', 9, 3);
$batchRepetitions = max(1, intdiv($iterations, $batchSize));
$batchObjects = $batchRepetitions * $batchSize;

$configuration = new Configuration(
    'MakerMill\\HydraType\\Benchmarks\\Generated\\NestedHydration',
    __DIR__ . '/../hydrators/nested-hydration-benchmark',
);
$factory = $configuration->getHydratorFactory();
$hydrators = [
    'flat' => $factory->create(FlatProfile::class),
    'one level' => $factory->create(OneLevelProfile::class),
    'two levels' => $factory->create(TwoLevelProfile::class),
];
$input = [
    'flat' => [
        'id' => '42',
        'displayName' => 'Ada',
        'streetName' => 'Main Street',
        'postalCode' => '12345',
        'countryCode' => 'SE',
    ],
    'one level' => [
        'id' => '42',
        'displayName' => 'Ada',
        'address' => [
            'streetName' => 'Main Street',
            'postalCode' => '12345',
            'countryCode' => 'SE',
        ],
    ],
    'two levels' => [
        'id' => '42',
        'displayName' => 'Ada',
        'address' => [
            'streetName' => 'Main Street',
            'postalCode' => '12345',
            'country' => ['countryCode' => 'SE'],
        ],
    ],
];
$expectedChecksum = 63;
$profileVerifier = nestedProfileVerifier($expectedChecksum);
$expectedOutput = $input;
foreach ($expectedOutput as &$output) {
    $output['id'] = 42;
}
unset($output);

$objects = [];
$dataSets = [];
$objectSets = [];
foreach ($hydrators as $name => $hydrator) {
    $objects[$name] = $hydrator->hydrate($input[$name]);
    $dataSets[$name] = array_fill(0, $batchSize, $input[$name]);
    $objectSets[$name] = array_fill(0, $batchSize, $objects[$name]);
}

$singleHydrationCases = [];
$singleExtractionCases = [];
$batchHydrationCases = [];
$batchExtractionCases = [];
foreach ($hydrators as $name => $hydrator) {
    $singleHydrationCases[$name] = new BenchmarkCase(
        nestedSingleHydration($hydrator, $input[$name], $iterations),
        $iterations,
        $profileVerifier,
    );
    $singleExtractionCases[$name] = new BenchmarkCase(
        nestedSingleExtraction($hydrator, $objects[$name], $iterations),
        $iterations,
        nestedExtractionVerifier($expectedOutput[$name]),
    );
    $batchHydrationCases[$name] = new BenchmarkCase(
        nestedBatchHydration($hydrator, $dataSets[$name], $batchRepetitions),
        $batchObjects,
        $profileVerifier,
    );
    $batchExtractionCases[$name] = new BenchmarkCase(
        nestedBatchExtraction($hydrator, $objectSets[$name], $batchRepetitions),
        $batchObjects,
        nestedExtractionVerifier($expectedOutput[$name]),
    );
}

printf("Nested hydration benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s objects/shape/sample | batch %s | %d samples\n",
    number_format($iterations),
    number_format($batchSize),
    $samples,
);

printNestedHydrationResults('hydrate()', BenchmarkRunner::measure($singleHydrationCases, $samples));
printNestedHydrationResults('hydrateMany()', BenchmarkRunner::measure($batchHydrationCases, $samples));
printNestedHydrationResults('extract()', BenchmarkRunner::measure($singleExtractionCases, $samples));
printNestedHydrationResults('extractMany()', BenchmarkRunner::measure($batchExtractionCases, $samples));
