<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\BenchmarkHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\BenchmarkTarget;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

function generateWriterFactoryTargetCode(
    string $namespace,
    string $className,
    int $propertyCount,
    bool $requiredConstructor,
): string {
    $properties = '';
    $checksum = [];
    for ($i = 1; $i <= $propertyCount; $i++) {
        $properties .= "private int \$property{$i} = 0;";
        $checksum[] = "\$this->property{$i}";
    }
    $constructor = $requiredConstructor ? 'public function __construct(string $required) {}' : '';

    return sprintf(
        'namespace %s; final class %s implements \\%s {%s%s public function checksum(): int {return %s;}}',
        $namespace,
        $className,
        BenchmarkTarget::class,
        $properties,
        $constructor,
        implode(' + ', $checksum),
    );
}

function generateWriterFactoryAssignments(int $propertyCount): string
{
    $assignments = '';
    for ($i = 1; $i <= $propertyCount; $i++) {
        $assignments .= "\$object->property{$i} = (int) \$data['property{$i}'];";
    }

    return $assignments;
}

function generateWriterFactoryHydratorCode(
    string $namespace,
    string $className,
    string $targetClassName,
    int $propertyCount,
    bool $requiredConstructor,
    bool $writerCreatesObject,
): string {
    $assignments = generateWriterFactoryAssignments($propertyCount);
    $reflectionProperty = $requiredConstructor && !$writerCreatesObject
        ? 'private \\ReflectionClass $reflectionClass;'
        : '';
    $reflectionSetup = $requiredConstructor
        ? '$reflectionClass = new \\ReflectionClass(' . $targetClassName . '::class);'
        : '';
    $reflectionAssignment = $requiredConstructor && !$writerCreatesObject
        ? '$this->reflectionClass = $reflectionClass;'
        : '';
    $currentCreation = $requiredConstructor
        ? '$this->reflectionClass->newInstanceWithoutConstructor()'
        : 'new ' . $targetClassName . '()';
    $factoryCreation = $requiredConstructor
        ? '$reflectionClass->newInstanceWithoutConstructor()'
        : 'new ' . $targetClassName . '()';

    if ($writerCreatesObject) {
        $use = $requiredConstructor ? ' use ($reflectionClass)' : '';
        $writer = sprintf(
            '$writer = \\Closure::bind(static function (array $data)%s: %s {' .
            '$object = %s;%s return $object;}, null, %s::class);',
            $use,
            $targetClassName,
            $factoryCreation,
            $assignments,
            $targetClassName,
        );
        $single = 'return ($this->writer)($data);';
        $many = '$results = [];foreach ($dataSet as $data) {$results[] = ($this->writer)($data);}return $results;';
    } else {
        $writer = sprintf(
            '$writer = \\Closure::bind(static function (%s $object, array $data): void {%s}, null, %s::class);',
            $targetClassName,
            $assignments,
            $targetClassName,
        );
        $single = '$object = ' . $currentCreation . ';($this->writer)($object, $data);return $object;';
        $many = '$results = [];foreach ($dataSet as $data) {$object = ' . $currentCreation . ';' .
            '($this->writer)($object, $data);$results[] = $object;}return $results;';
    }

    return sprintf(
        'namespace %s; final class %s implements \\%s {' .
        'private \\Closure $writer;%s public function __construct() {%s%s%s' .
        'if ($writer === null) {throw new \\RuntimeException("Unable to scope generated writer");}' .
        '$this->writer = $writer;}public function hydrate(array $data): %s {%s}' .
        'public function hydrateMany(array $dataSet): array {%s}}',
        $namespace,
        $className,
        BenchmarkHydrator::class,
        $reflectionProperty,
        $reflectionSetup,
        $reflectionAssignment,
        $writer,
        $targetClassName,
        $single,
        $many,
    );
}

/**
 * @return array{
 *     current: class-string<BenchmarkHydrator>,
 *     factory: class-string<BenchmarkHydrator>,
 *     data: array<string, int>,
 *     checksum: int
 * }
 */
function defineWriterFactoryClasses(int $propertyCount, bool $requiredConstructor): array
{
    $variant = $requiredConstructor ? 'RequiredConstructor' : 'NoConstructor';
    $namespace = 'MakerMill\\HydraType\\Benchmarks\\Generated\\WriterFactory' . $propertyCount . $variant;
    $targetClassName = $namespace . '\\Target';
    $currentClassName = $namespace . '\\CurrentHydrator';
    $factoryClassName = $namespace . '\\FactoryHydrator';

    if (!class_exists($targetClassName, false)) {
        eval(generateWriterFactoryTargetCode($namespace, 'Target', $propertyCount, $requiredConstructor));
        eval(generateWriterFactoryHydratorCode(
            $namespace,
            'CurrentHydrator',
            '\\' . $targetClassName,
            $propertyCount,
            $requiredConstructor,
            false,
        ));
        eval(generateWriterFactoryHydratorCode(
            $namespace,
            'FactoryHydrator',
            '\\' . $targetClassName,
            $propertyCount,
            $requiredConstructor,
            true,
        ));
    }

    if (!is_a($currentClassName, BenchmarkHydrator::class, true)) {
        throw new RuntimeException("{$currentClassName} is not a benchmark hydrator.");
    }
    if (!is_a($factoryClassName, BenchmarkHydrator::class, true)) {
        throw new RuntimeException("{$factoryClassName} is not a benchmark hydrator.");
    }

    $data = [];
    $checksum = 0;
    for ($i = 1; $i <= $propertyCount; $i++) {
        $data['property' . $i] = $i;
        $checksum += $i;
    }

    return [
        'current' => $currentClassName,
        'factory' => $factoryClassName,
        'data' => $data,
        'checksum' => $checksum,
    ];
}

/**
 * @param array<string, int> $data
 *
 * @return Closure(): BenchmarkTarget
 */
function createWriterFactorySingleOperation(BenchmarkHydrator $hydrator, array $data, int $iterations): Closure
{
    return static function () use ($hydrator, $data, $iterations): BenchmarkTarget {
        for ($i = 0; $i < $iterations; $i++) {
            $object = $hydrator->hydrate($data);
        }

        return $object;
    };
}

/**
 * @param non-empty-list<array<string, int>> $dataSet
 *
 * @return Closure(): BenchmarkTarget
 */
function createWriterFactoryBatchOperation(
    BenchmarkHydrator $hydrator,
    array $dataSet,
    int $repetitions,
): Closure {
    return static function () use ($hydrator, $dataSet, $repetitions): BenchmarkTarget {
        for ($i = 0; $i < $repetitions; $i++) {
            $objects = $hydrator->hydrateMany($dataSet);
        }

        return $objects[array_key_last($objects)];
    };
}

/**
 * @param array{current: Closure(): BenchmarkTarget, factory: Closure(): BenchmarkTarget} $operations
 *
 * @return array{current: list<float>, factory: list<float>}
 */
function benchmarkWriterFactoryPair(
    array $operations,
    int $objectsPerOperation,
    int $expectedChecksum,
    int $samples,
): array {
    $cases = [];
    foreach ($operations as $name => $operation) {
        $cases[$name] = new BenchmarkCase(
            $operation,
            $objectsPerOperation,
            static function (mixed $object) use ($name, $expectedChecksum): void {
                if (!$object instanceof BenchmarkTarget || $object->checksum() !== $expectedChecksum) {
                    throw new RuntimeException("{$name} failed hydration verification.");
                }
            },
        );
    }

    return BenchmarkRunner::measure($cases, $samples);
}

/** @param array{current: list<float>, factory: list<float>} $results */
function printWriterFactoryResult(string $scenario, array $results): void
{
    $current = Statistics::median($results['current']);
    $factory = Statistics::median($results['factory']);

    printf(
        "%-14s %12.2f %12.2f %9.2f%% %9.2fx %12.2f %12.2f\n",
        $scenario,
        $current,
        $factory,
        (1 - ($factory / $current)) * 100,
        $factory / $current,
        Statistics::percentile($results['current'], 0.95),
        Statistics::percentile($results['factory'], 0.95),
    );
}

$options = getopt('', ['objects::', 'warmup::', 'samples::', 'sizes::', 'batches::']);
$objectsPerSample = Options::integer($options, 'objects', 100_000);
$warmupObjects = Options::integer($options, 'warmup', 10_000);
$samples = Options::integer($options, 'samples', 11, 3);
$propertyCounts = Options::integerList($options, 'sizes', [1, 5, 10, 20, 50]);
$batchSizes = Options::integerList($options, 'batches', [1, 10, 100, 1_000]);

mt_srand(20260718);
printf("Writer-as-object-factory benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s objects/strategy/sample | %s warm-up | %d samples\n",
    number_format($objectsPerSample),
    number_format($warmupObjects),
    $samples,
);
printf("Property counts: %s | Batch sizes: %s\n", implode(', ', $propertyCounts), implode(', ', $batchSizes));

foreach ([false, true] as $requiredConstructor) {
    $variant = $requiredConstructor ? 'required constructor (reflection)' : 'no constructor (direct new)';
    foreach ($propertyCounts as $propertyCount) {
        $definition = defineWriterFactoryClasses($propertyCount, $requiredConstructor);
        $currentClass = $definition['current'];
        $factoryClass = $definition['factory'];
        $currentHydrator = new $currentClass();
        $factoryHydrator = new $factoryClass();

        printf("\n%s, %d %s\n", $variant, $propertyCount, $propertyCount === 1 ? 'property' : 'properties');
        printf(
            "%-14s %12s %12s %10s %9s %12s %12s\n",
            'Scenario',
            'current ns',
            'factory ns',
            'gain',
            'ratio',
            'current p95',
            'factory p95',
        );
        printf("%s\n", str_repeat('-', 91));

        $singleOperations = [
            'current' => createWriterFactorySingleOperation(
                $currentHydrator,
                $definition['data'],
                $objectsPerSample,
            ),
            'factory' => createWriterFactorySingleOperation(
                $factoryHydrator,
                $definition['data'],
                $objectsPerSample,
            ),
        ];
        foreach ([
            createWriterFactorySingleOperation($currentHydrator, $definition['data'], $warmupObjects),
            createWriterFactorySingleOperation($factoryHydrator, $definition['data'], $warmupObjects),
        ] as $warmup) {
            $warmup();
        }
        printWriterFactoryResult(
            'hydrate()',
            benchmarkWriterFactoryPair(
                $singleOperations,
                $objectsPerSample,
                $definition['checksum'],
                $samples,
            ),
        );

        foreach ($batchSizes as $batchSize) {
            $dataSet = array_fill(0, $batchSize, $definition['data']);
            $repetitions = max(1, (int) ceil($objectsPerSample / $batchSize));
            $objectsMeasured = $batchSize * $repetitions;
            $warmupRepetitions = max(1, (int) ceil($warmupObjects / $batchSize));
            foreach ([
                createWriterFactoryBatchOperation($currentHydrator, $dataSet, $warmupRepetitions),
                createWriterFactoryBatchOperation($factoryHydrator, $dataSet, $warmupRepetitions),
            ] as $warmup) {
                $warmup();
            }
            printWriterFactoryResult(
                'batch ' . $batchSize,
                benchmarkWriterFactoryPair(
                    [
                        'current' => createWriterFactoryBatchOperation($currentHydrator, $dataSet, $repetitions),
                        'factory' => createWriterFactoryBatchOperation($factoryHydrator, $dataSet, $repetitions),
                    ],
                    $objectsMeasured,
                    $definition['checksum'],
                    $samples,
                ),
            );
        }
    }
}
