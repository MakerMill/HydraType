<?php

declare(strict_types=1);

use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\HydratorCache;
use MakerMill\HydraType\Tests\Fixtures\PlainRecord;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

it('warms and verifies each requested artifact once without loading generated classes', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\Warmup' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/warmup-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $cache = new HydratorCache($configuration);
    $plainHydratorClass = (new ClassDescriptor(PlainRecord::class, $configuration))->getHydratorClassName();

    expect(class_exists($plainHydratorClass, false))->toBeFalse();
    $files = $cache->warm(SimpleUser::class, PlainRecord::class, SimpleUser::class);

    expect(array_keys($files))->toBe([SimpleUser::class, PlainRecord::class])
        ->and(class_exists($plainHydratorClass, false))->toBeFalse();
    foreach ($files as $file) {
        expect(is_file($file))->toBeTrue()
            ->and(token_get_all(readGeneratedFile($file), TOKEN_PARSE))->not->toBeEmpty();
    }

    $readOnly = new Configuration($namespace, $directory, CacheMode::ReadOnly);
    $object = objectOf(
        PlainRecord::class,
        $readOnly->getHydratorFactory()->create(PlainRecord::class)->hydrate([
            'id' => 42,
            'displayName' => 'Warmed',
        ]),
    );

    expect($object->values())->toBe(['id' => 42, 'displayName' => 'Warmed'])
        ->and(class_exists($plainHydratorClass, false))->toBeTrue();
});

it('always regenerates requested hydrators', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\ForcedWarmup' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/forced-warmup-' . bin2hex(random_bytes(6));
    $cache = new HydratorCache(new Configuration($namespace, $directory));
    $file = $cache->warm(PlainRecord::class)[PlainRecord::class];

    if (file_put_contents($file, '<?php // deliberately stale') === false) {
        throw new RuntimeException('Unable to prepare the forced warm-up test.');
    }

    $cache->warm(PlainRecord::class);

    expect(readGeneratedFile($file))->toContain('class PlainRecordHydrator_')
        ->and(token_get_all(readGeneratedFile($file), TOKEN_PARSE))->not->toBeEmpty();
});

it('rejects warm-up through a read-only configuration', function () {
    $directory = testHydratorDirectory() . '/read-only-warmup-' . bin2hex(random_bytes(6));
    $configuration = new Configuration(
        'MakerMill\\HydraType\\Tests\\ReadOnlyWarmup' . bin2hex(random_bytes(6)),
        $directory,
        CacheMode::ReadOnly,
    );

    expect(fn () => (new HydratorCache($configuration))->warm(PlainRecord::class))
        ->toThrow(HydrationException::class, 'Cache warm-up is not available in read-only mode')
        ->and(is_dir($directory))->toBeFalse();
});
