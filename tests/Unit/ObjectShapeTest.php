<?php

declare(strict_types=1);

use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\InheritedAccessibleRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\InheritedPrivateRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\InheritedReadonlyRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\OptionalUninitializedRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\PromotedRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\ReadonlyPropertyRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\ReadonlyRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\StaticRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\TraitRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\VisibilityRecord;

it('hydrates and extracts properties at every visibility', function () {
    $record = hydrateObject(testHydraType(), VisibilityRecord::class, [
        'publicId' => '12',
        'protectedName' => 'protected',
        'privateEnabled' => 'yes',
    ]);

    expect($record->values())->toBe([
        'publicId' => 12,
        'protectedName' => 'protected',
        'privateEnabled' => true,
    ])->and(testHydraType()->extract($record))->toBe([
        'publicId' => 12,
        'protectedName' => 'protected',
        'privateEnabled' => true,
    ]);
});

it('hydrates promoted properties without invoking the constructor', function () {
    $record = hydrateObject(testHydraType(), PromotedRecord::class, [
        'id' => '7',
        'name' => 'promoted',
    ]);

    expect($record->values())->toBe(['id' => 7, 'name' => 'promoted'])
        ->and(PromotedRecord::constructorCalls())->toBe(0);
});

it('hydrates and extracts readonly classes', function () {
    $record = hydrateObject(testHydraType(), ReadonlyRecord::class, [
        'id' => '9',
        'name' => 'readonly',
    ]);

    expect($record->values())->toBe(['id' => 9, 'name' => 'readonly'])
        ->and(testHydraType()->extract($record))->toBe(['id' => 9, 'name' => 'readonly']);
});

it('hydrates and extracts readonly properties declared on regular classes', function () {
    $record = hydrateObject(testHydraType(), ReadonlyPropertyRecord::class, [
        'id' => '10',
        'name' => 'regular class',
    ]);

    expect($record->values())->toBe(['id' => 10, 'name' => 'regular class'])
        ->and(testHydraType()->extract($record))->toBe(['id' => 10, 'name' => 'regular class']);
});

it('ignores static properties', function () {
    $record = hydrateObject(testHydraType(), StaticRecord::class, ['id' => '3']);

    expect($record->id())->toBe(3)
        ->and(StaticRecord::$metadata)->toBe('unchanged')
        ->and(testHydraType()->extract($record))->toBe(['id' => 3]);
});

it('hydrates and extracts properties declared by traits', function () {
    $record = hydrateObject(testHydraType(), TraitRecord::class, ['traitValue' => 'from trait']);

    expect($record->traitValue())->toBe('from trait')
        ->and(testHydraType()->extract($record))->toBe(['traitValue' => 'from trait']);
});

it('hydrates accessible properties inherited from an abstract parent', function () {
    $record = hydrateObject(testHydraType(), InheritedAccessibleRecord::class, [
        'publicParentId' => '4',
        'protectedParentName' => 'parent',
        'active' => 'true',
    ]);

    expect($record->values())->toBe([
        'publicParentId' => 4,
        'protectedParentName' => 'parent',
        'active' => true,
    ])->and(testHydraType()->extract($record))->toBe([
        'active' => true,
        'publicParentId' => 4,
        'protectedParentName' => 'parent',
    ]);
});

it('rejects inherited private properties during compilation', function () {
    expect(fn () => testHydraType()->hydrator(InheritedPrivateRecord::class))
        ->toThrow(HydrationException::class, "Private property 'privateParentValue' inherited");
});

it('rejects inherited readonly properties that are not portable across supported PHP versions', function () {
    expect(fn () => testHydraType()->hydrator(InheritedReadonlyRecord::class))
        ->toThrow(HydrationException::class, "Readonly property 'parentId' inherited");
});

it('rejects optional properties without a property default', function () {
    expect(fn () => testHydraType()->hydrator(OptionalUninitializedRecord::class))
        ->toThrow(HydrationException::class, "Optional property 'name'");
});
