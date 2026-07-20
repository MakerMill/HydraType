<?php

declare(strict_types=1);

use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Fixtures\IntersectionTypedRecord;
use MakerMill\HydraType\Tests\Fixtures\AbstractRecord;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;
use MakerMill\HydraType\Tests\Fixtures\UnbackedEnumRecord;
use MakerMill\HydraType\Tests\Fixtures\UnionTypedRecord;

it('rejects classes that do not exist', function () {
    $missingClass = 'MakerMill\\HydraType\\Tests\\Fixtures\\MissingRecord';
    $descriptor = new ReflectionClass(ClassDescriptor::class);

    expect(fn () => $descriptor->newInstance($missingClass, testConfiguration()))
        ->toThrow(HydrationException::class, "class '{$missingClass}' could not be found");
});

it('rejects classes that cannot be instantiated', function () {
    expect(fn () => testHydraType()->hydrator(AbstractRecord::class))
        ->toThrow(HydrationException::class, 'is not instantiable');
});

it('rejects union property types', function () {
    expect(fn () => testHydraType()->hydrator(UnionTypedRecord::class))
        ->toThrow(HydrationException::class, 'Union type not supported');
});

it('rejects intersection property types', function () {
    expect(fn () => testHydraType()->hydrator(IntersectionTypedRecord::class))
        ->toThrow(HydrationException::class, 'Intersection type not supported');
});

it('rejects unbacked enum properties', function () {
    expect(fn () => testHydraType()->hydrator(UnbackedEnumRecord::class))
        ->toThrow(HydrationException::class, 'A backed enum is required');
});

it('reports cache directories that cannot be created', function () {
    $configuration = new Configuration('MakerMill\\HydraType\\Tests\\Generated\\Unwritable', __FILE__);

    expect(fn () => (new HydraType($configuration))->hydrator(SimpleUser::class))
        ->toThrow(HydrationException::class, "Unable to create cache directory '" . __FILE__ . "'");
});
