<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\HydratableBenchmarkTarget;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @return class-string<HydratableBenchmarkTarget>
 */
function defineHydrationTarget(int $propertyCount): string
{
    $shortClassName = 'PrivatePropertyTarget' . $propertyCount;
    $className = 'MakerMill\\HydraType\\Benchmarks\\Generated\\' . $shortClassName;

    if (!class_exists($className, false)) {
        $properties = '';
        $assignments = '';
        $checksum = [];

        for ($i = 1; $i <= $propertyCount; $i++) {
            $properties .= "private int \$property{$i} = 0;";
            $assignments .= "\$this->property{$i} = \$data['property{$i}'];";
            $checksum[] = "\$this->property{$i}";
        }

        eval(sprintf(
            'namespace MakerMill\\HydraType\\Benchmarks\\Generated; final class %s ' .
            'implements \\MakerMill\\HydraType\\Benchmarks\\Fixtures\\HydratableBenchmarkTarget {' .
            '%s public function hydrate(array $data): void {%s}' .
            'public function checksum(): int {return %s;}}',
            $shortClassName,
            $properties,
            $assignments,
            implode(' + ', $checksum)
        ));
    }

    if (!is_a($className, HydratableBenchmarkTarget::class, true)) {
        throw new RuntimeException(sprintf('%s is not a benchmark target', $className));
    }

    return $className;
}

/**
 * @param class-string $className
 */
function createInstanceWriterFactory(string $className, int $propertyCount): Closure
{
    $assignments = '';
    for ($i = 1; $i <= $propertyCount; $i++) {
        $assignments .= "\$this->property{$i} = \$data['property{$i}'];";
    }

    $factory = eval(sprintf(
        'return static function (): \\Closure {' .
        'return function (array $data): void {%s};};',
        $assignments
    ));

    if (!$factory instanceof Closure) {
        throw new RuntimeException(sprintf('Unable to create instance writer for %s', $className));
    }

    return $factory;
}

/**
 * @param class-string $className
 */
function createStaticWriter(string $className, int $propertyCount): Closure
{
    $assignments = '';
    for ($i = 1; $i <= $propertyCount; $i++) {
        $assignments .= "\$object->property{$i} = \$data['property{$i}'];";
    }

    $writer = eval(sprintf(
        'return static function (\\%s $object, array $data): void {%s};',
        $className,
        $assignments
    ));

    if (!$writer instanceof Closure) {
        throw new RuntimeException(sprintf('Unable to create static writer for %s', $className));
    }

    $writer = Closure::bind($writer, null, $className);
    if ($writer === null) {
        throw new RuntimeException(sprintf('Unable to scope static writer for %s', $className));
    }

    return $writer;
}

/**
 * @param ReflectionClass<object>        $reflectionClass
 * @param array<int, ReflectionProperty> $reflectionProperties
 * @param array<string, int>             $data
 *
 * @return Closure(int): int
 */
function createUnrolledReflectionCase(
    ReflectionClass $reflectionClass,
    array $reflectionProperties,
    array $data,
    int $propertyCount
): Closure {
    $assignments = '';
    for ($i = 0; $i < $propertyCount; $i++) {
        $propertyNumber = $i + 1;
        $assignments .= "\$reflectionProperties[{$i}]->setValue(" .
            "\$object, \$data['property{$propertyNumber}']);";
    }

    $case = eval(sprintf(
        'return static function (int $iterations) use (' .
        '$reflectionClass, $reflectionProperties, $data): int {' .
        '$object = null; for ($i = 0; $i < $iterations; $i++) {' .
        '$object = $reflectionClass->newInstanceWithoutConstructor();%s}' .
        'return $object->checksum();};',
        $assignments
    ));

    if (!$case instanceof Closure) {
        throw new RuntimeException('Unable to create unrolled reflection benchmark case');
    }

    return $case;
}

/**
 * @return array{cases: array<string, Closure(int): int>, expected: int}
 */
function createHydrationCases(int $propertyCount): array
{
    $className = defineHydrationTarget($propertyCount);
    $reflectionClass = new ReflectionClass($className);
    $data = [];
    $expected = 0;
    $reflectionProperties = [];

    for ($i = 1; $i <= $propertyCount; $i++) {
        $data['property' . $i] = $i;
        $expected += $i;
        $reflectionProperties[] = $reflectionClass->getProperty('property' . $i);
    }

    $writerFactory = createInstanceWriterFactory($className, $propertyCount);
    $writerTemplate = $writerFactory();
    $staticWriter = createStaticWriter($className, $propertyCount);
    $unrolledReflectionCase = createUnrolledReflectionCase(
        $reflectionClass,
        $reflectionProperties,
        $data,
        $propertyCount
    );

    $cases = [
        'allocation only' => static function (int $iterations) use ($reflectionClass): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
            }

            return $object->checksum();
        },
        'in-class method (lower bound)' => static function (int $iterations) use ($reflectionClass, $data): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            $object->hydrate($data);
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                $object->hydrate($data);
            }

            return $object->checksum();
        },
        'current: create + bind' => static function (int $iterations) use (
            $className,
            $reflectionClass,
            $data,
            $writerFactory
        ): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            Closure::bind($writerFactory(), $object, $className)($data);
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                Closure::bind($writerFactory(), $object, $className)($data);
            }

            return $object->checksum();
        },
        'cached template + bind' => static function (int $iterations) use (
            $className,
            $reflectionClass,
            $data,
            $writerTemplate
        ): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            Closure::bind($writerTemplate, $object, $className)($data);
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                Closure::bind($writerTemplate, $object, $className)($data);
            }

            return $object->checksum();
        },
        'Closure::call cached template' => static function (int $iterations) use (
            $reflectionClass,
            $data,
            $writerTemplate
        ): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            $writerTemplate->call($object, $data);
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                $writerTemplate->call($object, $data);
            }

            return $object->checksum();
        },
        'pre-scoped static closure' => static function (int $iterations) use (
            $reflectionClass,
            $data,
            $staticWriter
        ): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            $staticWriter($object, $data);
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                $staticWriter($object, $data);
            }

            return $object->checksum();
        },
        'cached reflection (unrolled)' => $unrolledReflectionCase,
        'cached reflection (loop)' => static function (int $iterations) use (
            $reflectionClass,
            $reflectionProperties,
            $data
        ): int {
            $object = $reflectionClass->newInstanceWithoutConstructor();
            foreach ($reflectionProperties as $index => $property) {
                $property->setValue($object, $data['property' . ($index + 1)]);
            }
            for ($i = 1; $i < $iterations; $i++) {
                $object = $reflectionClass->newInstanceWithoutConstructor();
                foreach ($reflectionProperties as $index => $property) {
                    $property->setValue($object, $data['property' . ($index + 1)]);
                }
            }

            return $object->checksum();
        },
    ];

    return ['cases' => $cases, 'expected' => $expected];
}

/**
 * @param array<int, int> $propertyCounts
 *
 * @return array<int, array<string, array<int, float>>>
 */
function benchmarkHydrationMatrix(
    array $propertyCounts,
    int $iterations,
    int $warmupIterations,
    int $samples
): array {
    $matrix = [];

    foreach ($propertyCounts as $propertyCount) {
        ['cases' => $cases, 'expected' => $expected] = createHydrationCases($propertyCount);
        $benchmarkCases = [];
        foreach ($cases as $name => $case) {
            $expectedResult = $name === 'allocation only' ? 0 : $expected;
            $benchmarkCases[$name] = new BenchmarkCase(
                static fn (): int => $case($iterations),
                $iterations,
                static function (mixed $result) use ($name, $propertyCount, $expectedResult): void {
                    if ($result !== $expectedResult) {
                        $actual = is_int($result) ? $result : get_debug_type($result);
                        throw new RuntimeException(sprintf(
                            '%s produced an invalid result for %d properties: %s',
                            $name,
                            $propertyCount,
                            $actual,
                        ));
                    }
                },
                static fn (): int => $case($warmupIterations),
            );
        }

        $matrix[$propertyCount] = BenchmarkRunner::measure($benchmarkCases, $samples);
    }

    return $matrix;
}

/**
 * @param array<int, array<string, array<int, float>>> $matrix
 */
function printHydrationMatrix(array $matrix): void
{
    foreach ($matrix as $propertyCount => $results) {
        $allocationMedian = Statistics::median($results['allocation only']);
        $medians = [];
        foreach ($results as $name => $values) {
            $medians[$name] = Statistics::median($values);
        }

        $candidates = array_diff_key($medians, array_flip(['allocation only', 'in-class method (lower bound)']));
        if ($candidates === []) {
            throw new RuntimeException('No compiler-usable benchmark candidates were produced');
        }
        $fastestCandidate = array_search(min($candidates), $candidates, true);

        printf("\n%d private %s\n", $propertyCount, $propertyCount === 1 ? 'property' : 'properties');
        printf(
            "%-34s %14s %16s %14s\n",
            'Strategy',
            'median ns/obj',
            'net ns/property',
            'p95 ns/obj'
        );
        printf("%s\n", str_repeat('-', 84));

        foreach ($results as $name => $values) {
            $median = Statistics::median($values);
            $netPerProperty = max(0.0, $median - $allocationMedian) / $propertyCount;
            $marker = $name === $fastestCandidate ? ' *' : '';
            printf(
                "%-34s %14.2f %16.2f %14.2f%s\n",
                $name,
                $median,
                $netPerProperty,
                Statistics::percentile($values, 0.95),
                $marker
            );
        }
    }

    printf("\n* Fastest compiler-usable strategy at that property count.\n");
    printf("Net ns/property subtracts the allocation-only median and is approximate.\n");
}

$options = getopt('', ['iterations::', 'warmup::', 'samples::', 'sizes::']);
$iterations = Options::integer($options, 'iterations', 50_000);
$warmupIterations = Options::integer($options, 'warmup', 10_000);
$samples = Options::integer($options, 'samples', 15, 3);
$propertyCounts = Options::integerList($options, 'sizes', [1, 2, 3, 4, 5, 8, 10, 16, 20, 32]);

mt_srand(20260717);
printf("Private property hydration matrix\n");
printf("%s\n", Environment::summary());
printf(
    "%s objects/strategy/sample | %s warm-up objects | %d samples\n",
    number_format($iterations),
    number_format($warmupIterations),
    $samples
);
printf("Property counts: %s\n", implode(', ', $propertyCounts));

$matrix = benchmarkHydrationMatrix($propertyCounts, $iterations, $warmupIterations, $samples);
printHydrationMatrix($matrix);
