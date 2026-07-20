<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\PrivatePropertyTarget;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

function requireBenchmarkClosure(mixed $value): Closure
{
    if (!$value instanceof Closure) {
        throw new RuntimeException('Unable to create benchmark closure');
    }

    return $value;
}

/**
 * @param array<string, Closure(int): int> $cases
 *
 * @return array<string, array<int, float>>
 */
function benchmarkWrites(array $cases, int $iterations, int $warmupIterations, int $samples): array
{
    mt_srand(20260717);
    $benchmarkCases = [];
    foreach ($cases as $name => $case) {
        $benchmarkCases[$name] = new BenchmarkCase(
            static fn (): int => $case($iterations),
            $iterations,
            static function (mixed $result) use ($name, $iterations): void {
                if ($result !== $iterations - 1) {
                    $actual = is_int($result) ? $result : get_debug_type($result);
                    throw new RuntimeException(sprintf('%s produced an invalid result: %s', $name, $actual));
                }
            },
            static function () use ($case, $warmupIterations, $iterations): int {
                $case($warmupIterations);

                return $iterations - 1;
            },
        );
    }

    return BenchmarkRunner::measure($benchmarkCases, $samples);
}

/**
 * @param array<string, Closure(): object> $cases
 *
 * @return array<string, array<int, float>>
 */
function benchmarkSetup(array $cases, int $iterations, int $samples): array
{
    $results = array_fill_keys(array_keys($cases), []);

    foreach ($cases as $name => $case) {
        for ($sample = 0; $sample < $samples; $sample++) {
            $last = null;
            $start = hrtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $last = $case();
            }
            $elapsed = hrtime(true) - $start;

            if (!is_object($last)) {
                throw new RuntimeException(sprintf('%s did not produce an object', $name));
            }

            $results[$name][] = $elapsed / $iterations;
        }
    }

    return $results;
}

/**
 * @param array<string, array<int, float>> $results
 */
function printWriteResults(array $results): void
{
    $medians = [];
    foreach ($results as $name => $samples) {
        if ($samples === []) {
            throw new RuntimeException(sprintf('%s has no benchmark samples', $name));
        }
        $medians[$name] = Statistics::median($samples);
    }

    if ($medians === []) {
        throw new RuntimeException('No write benchmark results were produced');
    }

    asort($medians);
    $fastest = min($medians);

    printf("%-43s %12s %12s %12s %10s\n", 'Method', 'min ns/op', 'median', 'p95', 'relative');
    printf("%s\n", str_repeat('-', 95));

    foreach (array_keys($medians) as $name) {
        $samples = $results[$name];
        printf(
            "%-43s %12.2f %12.2f %12.2f %9.2fx\n",
            $name,
            min($samples),
            Statistics::median($samples),
            Statistics::percentile($samples, 0.95),
            Statistics::median($samples) / $fastest
        );
    }
}

/**
 * @param array<string, array<int, float>> $results
 */
function printSetupResults(array $results): void
{
    printf("%-43s %12s %12s %12s\n", 'Setup operation', 'min ns', 'median', 'p95');
    printf("%s\n", str_repeat('-', 83));

    foreach ($results as $name => $samples) {
        if ($samples === []) {
            throw new RuntimeException(sprintf('%s has no setup benchmark samples', $name));
        }
        printf(
            "%-43s %12.2f %12.2f %12.2f\n",
            $name,
            min($samples),
            Statistics::median($samples),
            Statistics::percentile($samples, 0.95)
        );
    }
}

$options = getopt('', ['iterations::', 'warmup::', 'samples::', 'setup-iterations::']);
$iterations = Options::integer($options, 'iterations', 250_000);
$warmupIterations = Options::integer($options, 'warmup', 50_000);
$samples = Options::integer($options, 'samples', 15, 3);
$setupIterations = Options::integer($options, 'setup-iterations', 10_000);

$target = new PrivatePropertyTarget();
$reflectionProperty = new ReflectionProperty(PrivatePropertyTarget::class, 'value');
$reflectionClass = new ReflectionClass(PrivatePropertyTarget::class);
$setter = $target->setValue(...);

$instanceWriterTemplate = requireBenchmarkClosure(eval(
    'return function (int $value): void {$this->value = $value;};'
));
$boundInstanceWriter = requireBenchmarkClosure(Closure::bind(
    $instanceWriterTemplate,
    $target,
    PrivatePropertyTarget::class
));
$bulkWriterTemplate = requireBenchmarkClosure(eval(
    'return function (int $count): void {' .
    'for ($i = 0; $i < $count; $i++) {$this->value = $i;}};'
));
$boundBulkWriter = requireBenchmarkClosure(Closure::bind(
    $bulkWriterTemplate,
    $target,
    PrivatePropertyTarget::class
));
$staticWriterTemplate = requireBenchmarkClosure(eval(
    'return static function (' . PrivatePropertyTarget::class . ' $object, int $value): void {' .
    '$object->value = $value;};'
));
$scopedStaticWriter = requireBenchmarkClosure(Closure::bind(
    $staticWriterTemplate,
    null,
    PrivatePropertyTarget::class
));
$magicWriteCase = requireBenchmarkClosure(eval(
    'return static function (int $count) use ($target): int {' .
    'for ($i = 0; $i < $count; $i++) {$target->value = $i;}' .
    'return $target->getValue();};'
));

$writeCases = [
    'in-class loop (lower bound)' => static function (int $count) use ($target): int {
        $target->setRepeatedly($count);

        return $target->getValue();
    },
    'public setter method' => static function (int $count) use ($target): int {
        for ($i = 0; $i < $count; $i++) {
            $target->setValue($i);
        }

        return $target->getValue();
    },
    'magic __set method' => $magicWriteCase,
    'first-class setter callable' => static function (int $count) use ($target, $setter): int {
        for ($i = 0; $i < $count; $i++) {
            $setter($i);
        }

        return $target->getValue();
    },
    'bound instance closure' => static function (int $count) use ($target, $boundInstanceWriter): int {
        for ($i = 0; $i < $count; $i++) {
            $boundInstanceWriter($i);
        }

        return $target->getValue();
    },
    'bound closure with loop inside' => static function (int $count) use ($target, $boundBulkWriter): int {
        $boundBulkWriter($count);

        return $target->getValue();
    },
    'scoped static closure' => static function (int $count) use ($target, $scopedStaticWriter): int {
        for ($i = 0; $i < $count; $i++) {
            $scopedStaticWriter($target, $i);
        }

        return $target->getValue();
    },
    'Closure::call per write' => static function (int $count) use ($target, $instanceWriterTemplate): int {
        for ($i = 0; $i < $count; $i++) {
            $instanceWriterTemplate->call($target, $i);
        }

        return $target->getValue();
    },
    'Closure::bind + invoke per write' => static function (int $count) use ($target, $instanceWriterTemplate): int {
        for ($i = 0; $i < $count; $i++) {
            Closure::bind($instanceWriterTemplate, $target, PrivatePropertyTarget::class)($i);
        }

        return $target->getValue();
    },
    'cached ReflectionProperty::setValue' => static function (int $count) use ($target, $reflectionProperty): int {
        for ($i = 0; $i < $count; $i++) {
            $reflectionProperty->setValue($target, $i);
        }

        return $target->getValue();
    },
    'cached ReflectionClass property lookup' => static function (int $count) use ($target, $reflectionClass): int {
        for ($i = 0; $i < $count; $i++) {
            $reflectionClass->getProperty('value')->setValue($target, $i);
        }

        return $target->getValue();
    },
    'new ReflectionProperty per write' => static function (int $count) use ($target): int {
        for ($i = 0; $i < $count; $i++) {
            (new ReflectionProperty(PrivatePropertyTarget::class, 'value'))->setValue($target, $i);
        }

        return $target->getValue();
    },
];

$setupCases = [
    'first-class setter callable' => static fn (): object => $target->setValue(...),
    'bind instance closure to object' => static fn (): object => Closure::bind(
        $instanceWriterTemplate,
        $target,
        PrivatePropertyTarget::class
    ),
    'bind static closure to class scope' => static fn (): object => Closure::bind(
        $staticWriterTemplate,
        null,
        PrivatePropertyTarget::class
    ),
    'new ReflectionProperty' => static fn (): object => new ReflectionProperty(
        PrivatePropertyTarget::class,
        'value'
    ),
    'new ReflectionClass + getProperty' => static fn (): object => (new ReflectionClass(
        PrivatePropertyTarget::class
    ))->getProperty('value'),
];

printf("Private property write benchmark\n");
printf("%s\n", Environment::summary());
printf(
    "%s writes/sample | %s warm-up writes | %d samples\n\n",
    number_format($iterations),
    number_format($warmupIterations),
    $samples
);

$writeResults = benchmarkWrites($writeCases, $iterations, $warmupIterations, $samples);
printWriteResults($writeResults);

printf("\nOne-time setup costs (%s repetitions/sample)\n", number_format($setupIterations));
$setupResults = benchmarkSetup($setupCases, $setupIterations, $samples);
printSetupResults($setupResults);

printf("\nRaw wall-clock timings; lower is better. Run on an idle machine with Xdebug disabled.\n");
