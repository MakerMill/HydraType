<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\BenchmarkHydrationContract;
use MakerMill\HydraType\Benchmarks\Fixtures\BenchmarkHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\BenchmarkTarget;
use MakerMill\HydraType\Benchmarks\Fixtures\SnakeCaseBenchmarkHydrationContract;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

function generateTargetCode(string $namespace, string $className, int $propertyCount): string
{
    $properties = '';
    $checksum = [];

    for ($i = 1; $i <= $propertyCount; $i++) {
        $properties .= "private int \$property{$i} = 0;";
        $checksum[] = "\$this->property{$i}";
    }

    return sprintf(
        'namespace %s; final class %s {%s public function checksum(): int {return %s;}}',
        $namespace,
        $className,
        $properties,
        implode(' + ', $checksum)
    );
}

function generateAssignments(int $propertyCount, string $objectExpression): string
{
    $assignments = '';

    for ($i = 1; $i <= $propertyCount; $i++) {
        $assignments .= sprintf(
            "%s->property%d = (int) \$data[\$conversionMap['property%d']];",
            $objectExpression,
            $i,
            $i
        );
    }

    return $assignments;
}

function generateHydratorCode(
    string $namespace,
    string $className,
    string $targetClassName,
    int $propertyCount,
    bool $preScoped
): string {
    $currentAssignments = generateAssignments($propertyCount, '$this');
    $staticAssignments = generateAssignments($propertyCount, '$object');

    $writerProperty = $preScoped ? 'private \\Closure $writer;' : '';
    $writerSetup = $preScoped
        ? sprintf(
            '$writer = \\Closure::bind(static function (%s $object, array $data, array $conversionMap, ' .
            '\\MakerMill\\HydraType\\Benchmarks\\Fixtures\\BenchmarkHydrationContract $contract): void {%s}, null, %s::class);' .
            'if ($writer === null) {throw new \\RuntimeException("Unable to scope generated writer");}' .
            '$this->writer = $writer;',
            $targetClassName,
            $staticAssignments,
            $targetClassName
        )
        : '';

    $writeObject = $preScoped
        ? '($this->writer)($object, $data, $conversionMap, $this->contract);'
        : sprintf(
            '$contract = $this->contract;' .
            '$writer = function (array $data, array $conversionMap) use ($contract): void {%s};' .
            '\\Closure::bind($writer, $object, %s::class)($data, $conversionMap);',
            $currentAssignments,
            $targetClassName
        );

    return sprintf(
        'namespace %s; final class %s implements ' .
        '\\MakerMill\\HydraType\\Benchmarks\\Fixtures\\BenchmarkHydrator {' .
        'private \\ReflectionClass $reflectionClass;%s' .
        'public function __construct(' .
        'private readonly \\MakerMill\\HydraType\\Benchmarks\\Fixtures\\BenchmarkHydrationContract $contract) {' .
        '$this->reflectionClass = new \\ReflectionClass(%s::class);%s}' .
        'public function hydrate(array $data): %s {' .
        'if ($data === []) {throw \\MakerMill\\HydraType\\HydrationException\\HydrationException::forEmptyData(%s::class);}' .
        '$conversionMap = $this->contract->renameArrayKeys($data);' .
        '$object = $this->reflectionClass->newInstanceWithoutConstructor();%s return $object;}' .
        'public function hydrateMany(array $dataSet): array {' .
        'if ($dataSet === []) {throw \\MakerMill\\HydraType\\HydrationException\\HydrationException::forEmptyData(%s::class);}' .
        '$conversionMap = $this->contract->renameArrayKeys($dataSet[0]);$results = [];' .
        'foreach ($dataSet as $data) {' .
        '$object = $this->reflectionClass->newInstanceWithoutConstructor();%s $results[] = $object;}' .
        'return $results;}}',
        $namespace,
        $className,
        $writerProperty,
        $targetClassName,
        $writerSetup,
        $targetClassName,
        $targetClassName,
        $writeObject,
        $targetClassName,
        $writeObject
    );
}

/**
 * @return array{
 *     target: class-string<BenchmarkTarget>,
 *     current: class-string<BenchmarkHydrator>,
 *     candidate: class-string<BenchmarkHydrator>,
 *     data: array<string, int>,
 *     checksum: int
 * }
 */
function defineStrategyClasses(int $propertyCount): array
{
    $namespace = 'MakerMill\\HydraType\\Benchmarks\\Generated\\Strategy' . $propertyCount;
    $targetShortName = 'Target';
    $targetClassName = $namespace . '\\' . $targetShortName;
    $currentClassName = $namespace . '\\CurrentHydrator';
    $candidateClassName = $namespace . '\\PreScopedHydrator';

    if (!class_exists($targetClassName, false)) {
        eval(str_replace(
            'final class ' . $targetShortName,
            'final class ' . $targetShortName . ' implements ' .
            '\\MakerMill\\HydraType\\Benchmarks\\Fixtures\\BenchmarkTarget',
            generateTargetCode($namespace, $targetShortName, $propertyCount)
        ));
        eval(generateHydratorCode(
            $namespace,
            'CurrentHydrator',
            '\\' . $targetClassName,
            $propertyCount,
            false
        ));
        eval(generateHydratorCode(
            $namespace,
            'PreScopedHydrator',
            '\\' . $targetClassName,
            $propertyCount,
            true
        ));
    }

    if (!is_a($targetClassName, BenchmarkTarget::class, true)) {
        throw new RuntimeException(sprintf('%s is not a benchmark target', $targetClassName));
    }
    if (!is_a($currentClassName, BenchmarkHydrator::class, true)) {
        throw new RuntimeException(sprintf('%s is not a benchmark hydrator', $currentClassName));
    }
    if (!is_a($candidateClassName, BenchmarkHydrator::class, true)) {
        throw new RuntimeException(sprintf('%s is not a benchmark hydrator', $candidateClassName));
    }

    $data = [];
    $checksum = 0;
    for ($i = 1; $i <= $propertyCount; $i++) {
        $data['property_' . $i] = $i;
        $checksum += $i;
    }

    return [
        'target' => $targetClassName,
        'current' => $currentClassName,
        'candidate' => $candidateClassName,
        'data' => $data,
        'checksum' => $checksum,
    ];
}

/**
 * @param array<string, mixed> $data
 *
 * @return Closure(): BenchmarkTarget
 */
function createSingleHydrationOperation(BenchmarkHydrator $hydrator, array $data, int $iterations): Closure
{
    return static function () use ($hydrator, $data, $iterations): BenchmarkTarget {
        $object = $hydrator->hydrate($data);
        for ($i = 1; $i < $iterations; $i++) {
            $object = $hydrator->hydrate($data);
        }

        return $object;
    };
}

/**
 * @param array<int, array<string, int>> $dataSet
 *
 * @return Closure(): BenchmarkTarget
 */
function createBatchHydrationOperation(
    BenchmarkHydrator $hydrator,
    array $dataSet,
    int $repetitions
): Closure {
    return static function () use ($hydrator, $dataSet, $repetitions): BenchmarkTarget {
        $objects = $hydrator->hydrateMany($dataSet);
        $lastObject = $objects[array_key_last($objects)];
        for ($i = 1; $i < $repetitions; $i++) {
            $objects = $hydrator->hydrateMany($dataSet);
            $lastObject = $objects[array_key_last($objects)];
        }

        return $lastObject;
    };
}

/**
 * @param array{current: Closure(): BenchmarkTarget, candidate: Closure(): BenchmarkTarget} $operations
 *
 * @return array{current: array<int, float>, candidate: array<int, float>}
 */
function benchmarkStrategyPair(
    array $operations,
    int $objectsPerOperation,
    int $expectedChecksum,
    int $samples
): array {
    $cases = [];
    foreach ($operations as $name => $operation) {
        $cases[$name] = new BenchmarkCase(
            $operation,
            $objectsPerOperation,
            static function (mixed $object) use ($name, $expectedChecksum): void {
                if (!$object instanceof BenchmarkTarget || $object->checksum() !== $expectedChecksum) {
                    throw new RuntimeException(sprintf('%s failed hydration verification', $name));
                }
            },
        );
    }

    return BenchmarkRunner::measure($cases, $samples);
}

/**
 * @param class-string<BenchmarkHydrator> $hydratorClass
 *
 * @return array<int, float>
 */
function benchmarkHydratorConstruction(
    string $hydratorClass,
    BenchmarkHydrationContract $contract,
    int $iterations,
    int $samples
): array {
    $results = [];

    for ($sample = 0; $sample < $samples; $sample++) {
        $hydrator = null;
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $hydrator = new $hydratorClass($contract);
        }
        $elapsed = hrtime(true) - $start;

        if (!$hydrator instanceof BenchmarkHydrator) {
            throw new RuntimeException('Hydrator construction failed');
        }

        $results[] = $elapsed / $iterations;
    }

    return $results;
}

/**
 * @param array{current: array<int, float>, candidate: array<int, float>} $results
 */
function printStrategyResult(string $scenario, array $results): void
{
    $currentMedian = Statistics::median($results['current']);
    $candidateMedian = Statistics::median($results['candidate']);
    $gain = (1 - ($candidateMedian / $currentMedian)) * 100;
    $ratio = $candidateMedian / $currentMedian;
    $throughput = 1_000 / $candidateMedian;

    printf(
        "%-18s %12.2f %12.2f %9.2f%% %9.2fx %11.2f %12.2f %12.2f\n",
        $scenario,
        $currentMedian,
        $candidateMedian,
        $gain,
        $ratio,
        $throughput,
        Statistics::percentile($results['current'], 0.95),
        Statistics::percentile($results['candidate'], 0.95)
    );
}

$options = getopt('', [
    'objects::',
    'warmup::',
    'samples::',
    'sizes::',
    'batches::',
    'setup-iterations::',
]);
$objectsPerSample = Options::integer($options, 'objects', 50_000);
$warmupObjects = Options::integer($options, 'warmup', 10_000);
$samples = Options::integer($options, 'samples', 15, 3);
$setupIterations = Options::integer($options, 'setup-iterations', 10_000);
$propertyCounts = Options::integerList($options, 'sizes', [1, 5, 10, 20]);
$batchSizes = Options::integerList($options, 'batches', [1, 10, 100, 1_000, 10_000]);

mt_srand(20260718);
printf("Generated hydrator strategy benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s hydrated objects/strategy/sample | %s warm-up objects | %d samples\n",
    number_format($objectsPerSample),
    number_format($warmupObjects),
    $samples
);
printf("Property counts: %s | Batch sizes: %s\n", implode(', ', $propertyCounts), implode(', ', $batchSizes));
printf("Contract: %s\n", SnakeCaseBenchmarkHydrationContract::class);

foreach ($propertyCounts as $propertyCount) {
    $definition = defineStrategyClasses($propertyCount);
    $contract = new SnakeCaseBenchmarkHydrationContract();
    $currentClass = $definition['current'];
    $candidateClass = $definition['candidate'];
    $currentHydrator = new $currentClass($contract);
    $candidateHydrator = new $candidateClass($contract);

    printf("\n%d private %s\n", $propertyCount, $propertyCount === 1 ? 'property' : 'properties');
    printf(
        "%-18s %12s %12s %10s %9s %11s %12s %12s\n",
        'Scenario',
        'current ns',
        'candidate ns',
        'gain',
        'ratio',
        'cand M/s',
        'current p95',
        'cand p95'
    );
    printf("%s\n", str_repeat('-', 112));

    $singleOperations = [
        'current' => createSingleHydrationOperation($currentHydrator, $definition['data'], $objectsPerSample),
        'candidate' => createSingleHydrationOperation($candidateHydrator, $definition['data'], $objectsPerSample),
    ];
    $singleWarmupOperations = [
        'current' => createSingleHydrationOperation($currentHydrator, $definition['data'], $warmupObjects),
        'candidate' => createSingleHydrationOperation($candidateHydrator, $definition['data'], $warmupObjects),
    ];
    foreach ($singleWarmupOperations as $operation) {
        $operation();
    }
    printStrategyResult(
        'hydrate()',
        benchmarkStrategyPair($singleOperations, $objectsPerSample, $definition['checksum'], $samples)
    );

    foreach ($batchSizes as $batchSize) {
        $dataSet = array_fill(0, $batchSize, $definition['data']);
        $repetitions = max(1, (int) ceil($objectsPerSample / $batchSize));
        $warmupRepetitions = max(1, (int) ceil($warmupObjects / $batchSize));
        $actualObjects = $repetitions * $batchSize;

        $batchOperations = [
            'current' => createBatchHydrationOperation($currentHydrator, $dataSet, $repetitions),
            'candidate' => createBatchHydrationOperation($candidateHydrator, $dataSet, $repetitions),
        ];
        $batchWarmupOperations = [
            'current' => createBatchHydrationOperation($currentHydrator, $dataSet, $warmupRepetitions),
            'candidate' => createBatchHydrationOperation($candidateHydrator, $dataSet, $warmupRepetitions),
        ];
        foreach ($batchWarmupOperations as $operation) {
            $operation();
        }

        printStrategyResult(
            'batch ' . $batchSize,
            benchmarkStrategyPair($batchOperations, $actualObjects, $definition['checksum'], $samples)
        );
    }

    $currentConstruction = benchmarkHydratorConstruction(
        $currentClass,
        $contract,
        $setupIterations,
        $samples
    );
    $candidateConstruction = benchmarkHydratorConstruction(
        $candidateClass,
        $contract,
        $setupIterations,
        $samples
    );
    printf(
        "construction       %12.2f %12.2f ns (current/candidate median; excluded above)\n",
        Statistics::median($currentConstruction),
        Statistics::median($candidateConstruction)
    );
}
