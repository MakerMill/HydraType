<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\Assertions\AssertionProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\Assertions\OneAssertionProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\Assertions\ThreeAssertionProfile;
use MakerMill\HydraType\Benchmarks\Fixtures\Assertions\UnassertedProfile;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\Interfaces\HydratorInterface;

require __DIR__ . '/../vendor/autoload.php';

function assertionVerify(mixed $result, int $checksum): void
{
    if (!$result instanceof AssertionProfile || $result->checksum() !== $checksum) {
        throw new RuntimeException('Assertion benchmark produced an invalid object.');
    }
}

/**
 * @param array<string, mixed> $data
 *
 * @return Closure(): object
 */
function assertionSingleHydration(HydratorInterface $hydrator, array $data, int $iterations): Closure
{
    return static function () use ($hydrator, $data, $iterations): object {
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $object = $hydrator->hydrate($data);
        }

        return $object;
    };
}

/**
 * @param non-empty-list<array<string, mixed>> $dataSet
 *
 * @return Closure(): object
 */
function assertionBatchHydration(HydratorInterface $hydrator, array $dataSet, int $repetitions): Closure
{
    return static function () use ($hydrator, $dataSet, $repetitions): object {
        for ($repetition = 0; $repetition < $repetitions; $repetition++) {
            $objects = $hydrator->hydrateMany($dataSet);
        }

        $lastKey = array_key_last($objects);
        if ($lastKey === null) {
            throw new RuntimeException('Assertion batch benchmark returned no objects.');
        }

        return $objects[$lastKey];
    };
}

/** @param array<string, list<float>> $results */
function printAssertionResults(string $title, array $results): void
{
    $baseline = Statistics::median($results['none']);

    printf("\n%s\n", $title);
    printf("%-12s %12s %12s %12s %12s\n", 'Assertions', 'median ns', 'p95 ns', 'vs none', 'M/s');
    printf("%s\n", str_repeat('-', 66));
    foreach ($results as $name => $values) {
        $median = Statistics::median($values);
        printf(
            "%-12s %12.2f %12.2f %11.2fx %12.2f\n",
            $name,
            $median,
            Statistics::percentile($values, 0.95),
            $median / $baseline,
            1_000 / $median,
        );
    }
}

$options = getopt('', ['objects::', 'batch::', 'samples::']);
$iterations = Options::integer($options, 'objects', 100_000);
$batchSize = Options::integer($options, 'batch', 1_000);
$samples = Options::integer($options, 'samples', 9, 3);
$batchRepetitions = max(1, intdiv($iterations, $batchSize));
$batchObjects = $batchRepetitions * $batchSize;

$configuration = new Configuration(
    'MakerMill\\HydraType\\Benchmarks\\Generated\\Assertions',
    __DIR__ . '/../hydrators/assertion-benchmark',
);
$factory = $configuration->getHydratorFactory();
$hydrators = [
    'none' => $factory->create(UnassertedProfile::class),
    'one' => $factory->create(OneAssertionProfile::class),
    'three' => $factory->create(ThreeAssertionProfile::class),
];
$data = ['id' => 1, 'score' => 42, 'rank' => 3, 'group' => 4, 'level' => 5];
$checksum = array_sum($data);
$dataSet = array_fill(0, $batchSize, $data);
$verifier = static function (mixed $result) use ($checksum): void {
    assertionVerify($result, $checksum);
};

$singleCases = [];
$batchCases = [];
foreach ($hydrators as $name => $hydrator) {
    $singleCases[$name] = new BenchmarkCase(
        assertionSingleHydration($hydrator, $data, $iterations),
        $iterations,
        $verifier,
    );
    $batchCases[$name] = new BenchmarkCase(
        assertionBatchHydration($hydrator, $dataSet, $batchRepetitions),
        $batchObjects,
        $verifier,
    );
}

printf("Compiled assertion benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s objects/case/sample | batch %s | %d samples\n",
    number_format($iterations),
    number_format($batchSize),
    $samples,
);

printAssertionResults('hydrate()', BenchmarkRunner::measure($singleCases, $samples));
printAssertionResults('hydrateMany()', BenchmarkRunner::measure($batchCases, $samples));
