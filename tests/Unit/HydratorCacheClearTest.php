<?php

declare(strict_types=1);

use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\HydratorCache;
use MakerMill\HydraType\Tests\Fixtures\PlainRecord;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

it('clears only requested generated hydrators and preserves their lock files', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\CacheClear' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/clear-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $cache = new HydratorCache($configuration);
    $generatedFiles = $cache->warm(SimpleUser::class, PlainRecord::class);

    $cache->clear(SimpleUser::class, SimpleUser::class);

    expect(is_file($generatedFiles[SimpleUser::class]))->toBeFalse()
        ->and(is_file($generatedFiles[SimpleUser::class] . '.lock'))->toBeTrue()
        ->and(is_file($generatedFiles[PlainRecord::class]))->toBeTrue();
});

it('does not create cache state when clearing a missing entry', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\MissingCacheClear' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/missing-clear-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);

    (new HydratorCache($configuration))->clear(SimpleUser::class);

    expect(is_dir($directory))->toBeFalse();
});

it('rejects cache clearing in read-only mode without modifying the cache', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\ReadOnlyCacheClear' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/read-only-clear-' . bin2hex(random_bytes(6));
    $automatic = new Configuration($namespace, $directory);
    $generatedFile = (new HydratorCache($automatic))->warm(SimpleUser::class)[SimpleUser::class];
    $readOnly = new Configuration($namespace, $directory, CacheMode::ReadOnly);

    expect(fn () => (new HydratorCache($readOnly))->clear(SimpleUser::class))
        ->toThrow(HydrationException::class, 'Cache clearing is not available in read-only mode')
        ->and(is_file($generatedFile))->toBeTrue();
});

it('regenerates a cleared hydrator on the next automatic resolution', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\RegenerateCacheClear' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/regenerate-clear-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $cache = new HydratorCache($configuration);
    $generatedFile = $cache->warm(SimpleUser::class)[SimpleUser::class];
    $cache->clear(SimpleUser::class);

    (new Configuration($namespace, $directory))->getHydratorFactory()->create(SimpleUser::class);

    expect(is_file($generatedFile))->toBeTrue();
});
