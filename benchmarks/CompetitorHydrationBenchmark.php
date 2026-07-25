<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use Crell\Serde\SerdeCommon;
use CuyZ\Valinor\MapperBuilder;
use EventSauce\ObjectHydrator\DefinitionProvider;
use EventSauce\ObjectHydrator\KeyFormatterWithoutConversion;
use EventSauce\ObjectHydrator\ObjectMapperCodeGenerator;
use GeneratedHydrator\Configuration as GeneratedHydratorConfiguration;
use Laminas\Hydrator\ReflectionHydrator;
use MakerMill\HydraType\Benchmarks\Fixtures\CompetitorRecord;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\Configuration;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Sunrise\Hydrator\Hydrator as SunriseHydrator;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

require __DIR__ . '/../vendor/autoload.php';

$competitorAutoloader = __DIR__ . '/competitors/vendor/autoload.php';
if (!is_file($competitorAutoloader)) {
    throw new RuntimeException(
        'Competitor dependencies are not installed. Run composer benchmark:competitors:install first.',
    );
}

// This autoloader is registered second and therefore prepended. GeneratedHydrator
// receives its compatible PHP Parser 4 without changing the root development tree.
require $competitorAutoloader;

/**
 * @param array<string, Closure(int): CompetitorRecord> $cases
 *
 * @return array<string, array{median: float, minimum: float, maximum: float}>
 */
function benchmarkCases(
    array $cases,
    int $expectedChecksum,
    int $operations,
    int $warmupOperations,
    int $samples,
): array {
    $benchmarkCases = [];
    foreach ($cases as $name => $case) {
        $benchmarkCases[$name] = new BenchmarkCase(
            static fn (): CompetitorRecord => $case($operations),
            $operations,
            static function (mixed $object) use ($name, $expectedChecksum): void {
                if (!$object instanceof CompetitorRecord || $object->checksum() !== $expectedChecksum) {
                    throw new RuntimeException("{$name} produced an invalid benchmark object.");
                }
            },
            static fn (): CompetitorRecord => $case($warmupOperations),
            static function (): void {
                gc_collect_cycles();
            },
        );
    }
    $timings = BenchmarkRunner::measure($benchmarkCases, $samples);

    $results = [];
    foreach ($timings as $name => $values) {
        $results[$name] = [
            'median' => Statistics::median($values),
            'minimum' => min($values),
            'maximum' => max($values),
        ];
    }

    uasort($results, static fn (array $left, array $right): int => $left['median'] <=> $right['median']);

    return $results;
}

/**
 * @param array<string, array{median: float, minimum: float, maximum: float}> $results
 */
function printResults(array $results): void
{
    $fastest = reset($results)['median'];

    printf("%-30s %12s %12s %12s %12s\n", 'Hydrator', 'median ns', 'min ns', 'max ns', 'relative');
    printf("%s\n", str_repeat('-', 84));

    foreach ($results as $name => $result) {
        printf(
            "%-30s %12.1f %12.1f %12.1f %11.2fx\n",
            $name,
            $result['median'],
            $result['minimum'],
            $result['maximum'],
            $result['median'] / $fastest,
        );
    }
}

/**
 * AutoMapper 10 requires PHP Parser 5 while GeneratedHydrator requires PHP Parser 4.
 * We run it in an isolated process so both competitors use their supported dependency.
 *
 * @return null|array{median: float, minimum: float, maximum: float}
 */
function benchmarkJoliCodeAutoMapper(int $operations, int $samples): ?array
{
    if (PHP_VERSION_ID < 80400) {
        return null;
    }

    $autoloadFile = __DIR__ . '/competitors/automapper/vendor/autoload.php';
    if (!is_file($autoloadFile)) {
        throw new RuntimeException(
            'JoliCode AutoMapper is not installed. Run composer benchmark:competitors:install first.',
        );
    }

    $command = [
        PHP_BINARY,
        '-d',
        'xdebug.mode=off',
        __DIR__ . '/JoliCodeAutoMapperBenchmark.php',
        (string) $operations,
        (string) $samples,
    ];
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the JoliCode AutoMapper benchmark worker.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException(
            "JoliCode AutoMapper benchmark failed with exit code {$exitCode}: {$errorOutput}",
        );
    }

    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    if (
        !is_array($result)
        || !isset($result['median'], $result['minimum'], $result['maximum'])
        || !is_numeric($result['median'])
        || !is_numeric($result['minimum'])
        || !is_numeric($result['maximum'])
    ) {
        throw new RuntimeException('JoliCode AutoMapper benchmark returned an invalid result.');
    }

    return [
        'median' => (float) $result['median'],
        'minimum' => (float) $result['minimum'],
        'maximum' => (float) $result['maximum'],
    ];
}

$data = [
    'id' => 42,
    'userName' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'city' => 'London',
    'active' => true,
];
$expectedChecksum = (new CompetitorRecord(...$data))->checksum();

$cacheDirectory = __DIR__ . '/../hydrators/competitors';
if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
    throw new RuntimeException("Unable to create benchmark cache directory {$cacheDirectory}.");
}

$hydraType = (new Configuration(hydratorDirectory: $cacheDirectory))
    ->getHydratorFactory()
    ->create(CompetitorRecord::class);

$generatedHydratorConfiguration = new GeneratedHydratorConfiguration(CompetitorRecord::class);
$generatedHydratorConfiguration->setGeneratedClassesTargetDir($cacheDirectory);
$generatedHydratorClass = $generatedHydratorConfiguration->createFactory()->getHydratorClass();
$generatedHydrator = new $generatedHydratorClass();

$eventSauceClass = 'MakerMill\\HydraType\\Benchmarks\\Generated\\EventSauceMapper';
$eventSauceDefinitions = new DefinitionProvider(keyFormatter: new KeyFormatterWithoutConversion());
$eventSauceCode = (new ObjectMapperCodeGenerator($eventSauceDefinitions))->dump(
    [CompetitorRecord::class],
    $eventSauceClass,
);
eval(substr($eventSauceCode, 5));
$eventSauce = new $eventSauceClass();

$laminas = new ReflectionHydrator();
$serde = new SerdeCommon();
$patchlevel = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->build();
$sunrise = new SunriseHydrator();
$symfony = new PropertyNormalizer();
$valinor = (new MapperBuilder())->mapper();

/** @var array<string, Closure(int): CompetitorRecord> $cases */
$cases = [
    'HydraType' => static function (int $operations) use ($hydraType, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $hydraType->hydrate($data);
        }

        return $object;
    },
    'Ocramius GeneratedHydrator' => static function (int $operations) use ($generatedHydrator, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            $object = new CompetitorRecord();
            $generatedHydrator->hydrate($data, $object);
        }

        return $object;
    },
    'EventSauce generated' => static function (int $operations) use ($eventSauce, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $eventSauce->hydrateObject(CompetitorRecord::class, $data);
        }

        return $object;
    },
    'Laminas ReflectionHydrator' => static function (int $operations) use ($laminas, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            $object = new CompetitorRecord();
            $laminas->hydrate($data, $object);
        }

        return $object;
    },
    'Crell Serde (array)' => static function (int $operations) use ($serde, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $serde->deserialize($data, from: 'array', to: CompetitorRecord::class);
        }

        return $object;
    },
    'Patchlevel Hydrator' => static function (int $operations) use ($patchlevel, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $patchlevel->hydrate(CompetitorRecord::class, $data);
        }

        return $object;
    },
    'Sunrise Hydrator' => static function (int $operations) use ($sunrise, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $sunrise->hydrate(CompetitorRecord::class, $data);
        }

        return $object;
    },
    'Symfony PropertyNormalizer' => static function (int $operations) use ($symfony, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $symfony->denormalize($data, CompetitorRecord::class);
        }

        return $object;
    },
    'Valinor' => static function (int $operations) use ($valinor, $data): CompetitorRecord {
        for ($i = 0; $i < $operations; $i++) {
            /** @var CompetitorRecord $object */
            $object = $valinor->map(CompetitorRecord::class, $data);
        }

        return $object;
    },
];

$operations = (int) ($_SERVER['argv'][1] ?? 20_000);
$samples = (int) ($_SERVER['argv'][2] ?? 9);
$warmupOperations = min(2_000, $operations);

if ($operations < 1 || $samples < 1) {
    throw new InvalidArgumentException('Operations and samples must both be positive integers.');
}

printf("Hydration competitor benchmark (PHP %s)\n", PHP_VERSION);
printf("%d operations per sample, %d samples, %d warm-up operations\n", $operations, $samples, $warmupOperations);
printf("Correctly typed camelCase input; object creation included; setup and code generation excluded.\n\n");

$results = benchmarkCases($cases, $expectedChecksum, $operations, $warmupOperations, $samples);
$joliCodeResult = benchmarkJoliCodeAutoMapper($operations, $samples);
if ($joliCodeResult === null) {
    printf("JoliCode AutoMapper 10 excluded: it requires PHP 8.4 or newer.\n\n");
} else {
    $results['JoliCode AutoMapper 10'] = $joliCodeResult;
    uasort($results, static fn (array $left, array $right): int => $left['median'] <=> $right['median']);
}

printResults($results);
