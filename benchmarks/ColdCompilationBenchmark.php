<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\ColdCompilation\ColdCompilationTarget;
use MakerMill\HydraType\Benchmarks\Support\Statistics;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\CacheFingerprint;
use MakerMill\HydraType\ClassAnalyzer;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydratorCache;
use MakerMill\HydraType\HydratorCacheFile;
use MakerMill\HydraType\HydratorCompiler;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param Closure(): mixed $operation
 *
 * @return list<float>
 */
function measureColdCompilation(Closure $operation, int $iterations, int $samples): array
{
    $operation();
    $values = [];
    for ($sample = 0; $sample < $samples; $sample++) {
        $start = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $operation();
        }
        $values[] = (hrtime(true) - $start) / $iterations / 1_000;
    }

    return $values;
}

$options = getopt('', ['samples::', 'scale::']);
$samples = Options::integer($options, 'samples', 9);
$scale = Options::integer($options, 'scale', 1);
$namespace = 'MakerMill\\HydraType\\Benchmarks\\Generated\\ColdCompilation';
$directory = __DIR__ . '/../hydrators/cold-compilation-benchmark';
$configuration = new Configuration($namespace, $directory);
$descriptor = new ClassDescriptor(ColdCompilationTarget::class, $configuration);
$compiler = new HydratorCompiler($descriptor, $configuration);
$analyzer = new ClassAnalyzer($descriptor);
$dependencies = $analyzer->getCacheDependencies();
$generateCode = new ReflectionMethod(HydratorCompiler::class, 'generateCode');
$source = $generateCode->invoke($compiler);
if (!is_string($source)) {
    throw new RuntimeException('Cold-compilation benchmark could not generate hydrator source.');
}
$cacheFile = new HydratorCacheFile($descriptor);
$cache = new HydratorCache($configuration);

$cases = [
    'Analyze class' => [
        'iterations' => 2_000 * $scale,
        'operation' => static fn (): ClassAnalyzer => new ClassAnalyzer($descriptor),
    ],
    'Initialize compiler' => [
        'iterations' => 1_000 * $scale,
        'operation' => static fn (): HydratorCompiler => new HydratorCompiler($descriptor, $configuration),
    ],
    'Discover dependencies' => [
        'iterations' => 500 * $scale,
        'operation' => static fn (): array => $analyzer->getCacheDependencies(),
    ],
    'Fingerprint sources' => [
        'iterations' => 500 * $scale,
        'operation' => static fn (): string => CacheFingerprint::header($dependencies),
    ],
    'Generate source' => [
        'iterations' => 250 * $scale,
        'operation' => static fn (): mixed => $generateCode->invoke($compiler),
    ],
    'Parse generated source' => [
        'iterations' => 500 * $scale,
        'operation' => static fn (): array => token_get_all($source, TOKEN_PARSE),
    ],
    'Publish source' => [
        'iterations' => 100 * $scale,
        'operation' => static fn (): mixed => $cacheFile->compile(static fn (): string => $source, true),
    ],
    'Compile target' => [
        'iterations' => 100 * $scale,
        'operation' => static fn (): mixed => (new HydratorCompiler($descriptor, $configuration))->compile(true),
    ],
    'Warm target graph' => [
        'iterations' => 50 * $scale,
        'operation' => static fn (): array => $cache->warm(ColdCompilationTarget::class),
    ],
];

printf("Cold compilation benchmark (PHP %s)\n", PHP_VERSION);
printf("Feature-rich target with 10 properties, %d samples, scale %d\n\n", $samples, $scale);
printf("%-22s %12s %12s %12s\n", 'Stage', 'median us', 'p95 us', 'iterations');
printf("%s\n", str_repeat('-', 64));

foreach ($cases as $name => $case) {
    $values = measureColdCompilation($case['operation'], $case['iterations'], $samples);
    printf(
        "%-22s %12.2f %12.2f %12d\n",
        $name,
        Statistics::median($values),
        Statistics::percentile($values, 0.95),
        $case['iterations'],
    );
}
