<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

it('uses automatic cache management by default', function () {
    expect((new Configuration())->getCacheMode())->toBe(CacheMode::Auto);
});

it('uses isolated generated class names for different configurations', function () {
    $first = new ClassDescriptor(SimpleUser::class, testConfiguration('FirstGenerated'));
    $second = new ClassDescriptor(SimpleUser::class, testConfiguration('SecondGenerated'));

    expect($first->getHydratorClassName())->not->toBe($second->getHydratorClassName());
});

it('uses the same generated identity in automatic and read-only modes', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\SharedCacheMode';
    $directory = testHydratorDirectory() . '/shared-cache-mode';
    $automatic = new ClassDescriptor(
        SimpleUser::class,
        new Configuration($namespace, $directory, CacheMode::Auto),
    );
    $readOnly = new ClassDescriptor(
        SimpleUser::class,
        new Configuration($namespace, $directory, CacheMode::ReadOnly),
    );

    expect($readOnly->getHydratorClassName())->toBe($automatic->getHydratorClassName())
        ->and($readOnly->getHydratorFilePath())->toBe($automatic->getHydratorFilePath());
});

it('loads an existing cache entry without freshness checks or writes in read-only mode', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\ReadOnlyGenerated' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/read-only-' . bin2hex(random_bytes(6));
    $automatic = new Configuration($namespace, $directory, CacheMode::Auto);
    $automatic->getHydratorFactory()->create(SimpleUser::class);

    $descriptor = new ClassDescriptor(SimpleUser::class, $automatic);
    $generatedFile = $descriptor->getHydratorFilePath();
    $lockFile = $generatedFile . '.lock';
    if (!unlink($lockFile) || !touch($generatedFile, 1)) {
        throw new RuntimeException('Unable to prepare the read-only cache test.');
    }

    $readOnly = new Configuration($namespace, $directory, CacheMode::ReadOnly);
    $hydrator = $readOnly->getHydratorFactory()->create(SimpleUser::class);
    $object = objectOf(SimpleUser::class, $hydrator->hydrate([
        'id' => 42,
        'userName' => 'Read Only',
        'password' => 'secret',
    ]));

    expect($object->getUserName())->toBe('Read Only')
        ->and(filemtime($generatedFile))->toBe(1)
        ->and(is_file($lockFile))->toBeFalse();
});

it('does not create a missing cache directory in read-only mode', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\MissingReadOnly' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/missing-read-only-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory, CacheMode::ReadOnly);
    $descriptor = new ClassDescriptor(SimpleUser::class, $configuration);

    expect(fn () => $configuration->getHydratorFactory()->create(SimpleUser::class))
        ->toThrow(
            HydrationException::class,
            "Cache entry for '" . SimpleUser::class . "' is missing in read-only mode: "
            . $descriptor->getHydratorFilePath(),
        )
        ->and(is_dir($directory))->toBeFalse();
});

it('does not regenerate a valid hydrator because its timestamp changed', function () {
    $configuration = testConfiguration();
    $descriptor = new ClassDescriptor(SimpleUser::class, $configuration);
    $configuration->getHydratorFactory()->create(SimpleUser::class);

    $generatedFile = $descriptor->getHydratorFilePath();
    $generatedModifiedTime = filemtime($generatedFile);
    if ($generatedModifiedTime === false || !touch($generatedFile, $generatedModifiedTime - 3600)) {
        throw new RuntimeException('Unable to change the generated hydrator timestamp.');
    }
    clearstatcache(true, $generatedFile);
    $changedModifiedTime = filemtime($generatedFile);
    if ($changedModifiedTime === false || $changedModifiedTime === $generatedModifiedTime) {
        throw new RuntimeException('Generated hydrator timestamp did not change.');
    }

    $configuration = testConfiguration();
    $configuration->getHydratorFactory()->create(SimpleUser::class);

    clearstatcache(true, $generatedFile);
    expect(filemtime($generatedFile))->toBe($changedModifiedTime);
});

it('reuses a hydrator from the in-memory cache', function () {
    $factory = testConfiguration()->getHydratorFactory();
    $firstHydrator = $factory->create(SimpleUser::class);
    $secondHydrator = $factory->create(SimpleUser::class);

    expect($secondHydrator)->toBe($firstHydrator);
});

it('publishes one complete hydrator during concurrent compilation', function () {
    $cacheDirectory = testHydratorDirectory() . '/concurrent-' . bin2hex(random_bytes(6));
    $startFile = testHydratorDirectory() . '/start-' . bin2hex(random_bytes(6));
    $namespace = 'MakerMill\\HydraType\\Tests\\Concurrent' . bin2hex(random_bytes(6));
    $worker = dirname(__DIR__) . '/Support/concurrent-hydrator-worker.php';
    $processes = [];

    for ($index = 0; $index < 8; $index++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', $worker, $cacheDirectory, $startFile, $namespace],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start a cache-concurrency worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }

    if (!touch($startFile)) {
        throw new RuntimeException('Unable to signal the cache-concurrency workers.');
    }

    foreach ($processes as [$process, $standardOutput, $standardError]) {
        $output = stream_get_contents($standardOutput);
        $error = stream_get_contents($standardError);
        fclose($standardOutput);
        fclose($standardError);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException("Cache-concurrency worker failed:\n{$output}{$error}");
        }
        expect($exitCode)->toBe(0);
    }

    $configuration = new \MakerMill\HydraType\Configuration(
        hydratorNamespace: $namespace,
        hydratorDirectory: $cacheDirectory,
    );
    $descriptor = new ClassDescriptor(SimpleUser::class, $configuration);
    $generatedFile = $descriptor->getHydratorFilePath();
    $source = readGeneratedFile($generatedFile);

    expect(token_get_all($source, TOKEN_PARSE))->not->toBeEmpty()
        ->and(\MakerMill\HydraType\CacheFingerprint::matches($generatedFile))->toBeTrue()
        ->and(glob($generatedFile . '.*.tmp'))->toBe([])
        ->and(is_file($generatedFile . '.lock'))->toBeTrue();
});
