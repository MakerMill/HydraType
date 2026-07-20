<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\ObjectCreationTarget;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

function generateCreationTargetCode(
    string $namespace,
    string $className,
    int $propertyCount,
    bool $requiredConstructor,
): string {
    $properties = '';
    $checksum = [];
    for ($i = 1; $i <= $propertyCount; $i++) {
        $properties .= "private int \$property{$i} = {$i};";
        $checksum[] = "\$this->property{$i}";
    }

    $constructor = $requiredConstructor ? 'public function __construct(string $required) {}' : '';

    return sprintf(
        'namespace %s; final class %s implements \\%s {%s%s public function checksum(): int {return %s;}}',
        $namespace,
        $className,
        ObjectCreationTarget::class,
        $properties,
        $constructor,
        $checksum === [] ? '0' : implode(' + ', $checksum),
    );
}

/** @return class-string<ObjectCreationTarget> */
function defineCreationTarget(int $propertyCount, bool $requiredConstructor): string
{
    $variant = $requiredConstructor ? 'RequiredConstructor' : 'NoConstructor';
    $namespace = 'MakerMill\\HydraType\\Benchmarks\\Generated\\Creation' . $propertyCount . $variant;
    $className = $namespace . '\\Target';

    if (!class_exists($className, false)) {
        eval(generateCreationTargetCode($namespace, 'Target', $propertyCount, $requiredConstructor));
    }
    if (!is_a($className, ObjectCreationTarget::class, true)) {
        throw new RuntimeException("{$className} is not an object creation target.");
    }

    return $className;
}

/**
 * @param class-string<ObjectCreationTarget> $className
 *
 * @return array<string, Closure(int): ObjectCreationTarget>
 */
function createCreationOperations(string $className, bool $requiredConstructor): array
{
    $reflection = new ReflectionClass($className);
    $prototype = $reflection->newInstanceWithoutConstructor();
    $serialized = sprintf('O:%d:"%s":0:{}', strlen($className), $className);

    $operations = [
        'cached reflection' => static function (int $iterations) use ($reflection): ObjectCreationTarget {
            for ($i = 0; $i < $iterations; $i++) {
                $object = $reflection->newInstanceWithoutConstructor();
            }

            return $object;
        },
        'clone prototype' => static function (int $iterations) use ($prototype): ObjectCreationTarget {
            for ($i = 0; $i < $iterations; $i++) {
                $object = clone $prototype;
            }

            return $object;
        },
        'unserialize' => static function (int $iterations) use ($serialized): ObjectCreationTarget {
            for ($i = 0; $i < $iterations; $i++) {
                $object = unserialize($serialized, ['allowed_classes' => true]);
            }
            if (!$object instanceof ObjectCreationTarget) {
                throw new RuntimeException('Unserialize did not return the expected target.');
            }

            return $object;
        },
        'uncached reflection' => static function (int $iterations) use ($className): ObjectCreationTarget {
            for ($i = 0; $i < $iterations; $i++) {
                $object = (new ReflectionClass($className))->newInstanceWithoutConstructor();
            }

            return $object;
        },
    ];

    if (!$requiredConstructor) {
        /** @var Closure(int): ObjectCreationTarget $directNew */
        $directNew = eval(sprintf(
            'return static function (int $iterations): \\%s {' .
            'for ($i = 0; $i < $iterations; $i++) {$object = new \\%s();} return $object;};',
            ObjectCreationTarget::class,
            $className,
        ));
        $operations = ['direct new' => $directNew] + $operations;
        $operations['cached reflection new'] = static function (int $iterations) use ($reflection): ObjectCreationTarget {
            for ($i = 0; $i < $iterations; $i++) {
                $object = $reflection->newInstance();
            }

            return $object;
        };
    }

    return $operations;
}

/**
 * @param array<string, Closure(int): ObjectCreationTarget> $operations
 *
 * @return array<string, list<float>>
 */
function benchmarkCreationOperations(
    array $operations,
    int $iterations,
    int $warmupIterations,
    int $samples,
    int $expectedChecksum,
): array {
    $cases = [];
    foreach ($operations as $name => $operation) {
        $cases[$name] = new BenchmarkCase(
            static fn (): ObjectCreationTarget => $operation($iterations),
            $iterations,
            static function (mixed $object) use ($name, $expectedChecksum): void {
                if (!$object instanceof ObjectCreationTarget || $object->checksum() !== $expectedChecksum) {
                    throw new RuntimeException("{$name} failed creation verification.");
                }
            },
            static fn (): ObjectCreationTarget => $operation($warmupIterations),
        );
    }

    return BenchmarkRunner::measure($cases, $samples);
}

/** @param array<string, list<float>> $results */
function printCreationResults(array $results): void
{
    uasort(
        $results,
        static fn (array $left, array $right): int =>
            Statistics::median($left) <=> Statistics::median($right),
    );
    $fastest = Statistics::median(reset($results));

    printf("%-24s %12s %12s %10s %12s\n", 'Strategy', 'median ns', 'p95 ns', 'relative', 'objects M/s');
    printf("%s\n", str_repeat('-', 76));
    foreach ($results as $name => $samples) {
        $median = Statistics::median($samples);
        printf(
            "%-24s %12.2f %12.2f %9.2fx %12.2f\n",
            $name,
            $median,
            Statistics::percentile($samples, 0.95),
            $median / $fastest,
            1_000 / $median,
        );
    }
}

$options = getopt('', ['objects::', 'warmup::', 'samples::', 'sizes::']);
$iterations = Options::integer($options, 'objects', 250_000);
$warmupIterations = Options::integer($options, 'warmup', 25_000);
$samples = Options::integer($options, 'samples', 11, 3);
$propertyCounts = Options::integerList($options, 'sizes', [0, 1, 5, 10, 20, 50], 0);

mt_srand(20260718);
printf("Object creation benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s creations/strategy/sample | %s warm-up | %d samples\n",
    number_format($iterations),
    number_format($warmupIterations),
    $samples,
);
printf("Property counts: %s\n", implode(', ', $propertyCounts));

foreach ([false, true] as $requiredConstructor) {
    $variant = $requiredConstructor ? 'required constructor (bypassed)' : 'no constructor';
    foreach ($propertyCounts as $propertyCount) {
        $className = defineCreationTarget($propertyCount, $requiredConstructor);
        $operations = createCreationOperations($className, $requiredConstructor);
        $expectedChecksum = (int) (($propertyCount * ($propertyCount + 1)) / 2);
        $results = benchmarkCreationOperations(
            $operations,
            $iterations,
            $warmupIterations,
            $samples,
            $expectedChecksum,
        );

        printf("\n%s, %d %s\n", $variant, $propertyCount, $propertyCount === 1 ? 'property' : 'properties');
        printCreationResults($results);
    }
}
