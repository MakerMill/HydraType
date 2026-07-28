<?php

declare(strict_types=1);

use MakerMill\HydraType\Assertions\NotEmpty;
use MakerMill\HydraType\ClassAnalyzer;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydrationException\AssertionException;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Consumer\AssertedValue;
use MakerMill\HydraType\Tests\Consumer\Assertions\MinimumLength;
use MakerMill\HydraType\Tests\Fixtures\AssertedRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\AssertedAddress;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('asserts the transformed and converted value immediately before assignment', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, AssertedRecord::class, [
        'name' => '  accepted  ',
        'tags' => ['fast'],
        'note' => null,
    ]);
    $descriptor = new ClassDescriptor(AssertedRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());
    $writer = GeneratedHydratorInspector::closureBody($source, 'createCamelWriter');
    $reader = GeneratedHydratorInspector::closureBody($source, 'createCamelReader');

    expect($record->values())->toBe([
        'name' => 'accepted',
        'tags' => ['fast'],
        'note' => null,
        'label' => 'fallback',
    ])->and($writer)
        ->toContain("\$hydraAssertionValue = trim((string) (\$data['name'] ??")
        ->toContain("if (!(\$hydraAssertionValue !== '' && \$hydraAssertionValue !== []))")
        ->toContain('$object->name = $hydraAssertionValue;')
        ->toContain("\$hydraAssertionValue === null || (\$hydraAssertionValue !== ''")
        ->toContain("if (array_key_exists('label', \$data)) {")
        ->and(str_contains($reader, 'hydraAssertionValue'))->toBeFalse()
        ->and(str_contains($reader, 'AssertionException'))->toBeFalse();
});

it('checks present nullable and optional values while accepting their bypass paths', function () {
    $valid = [
        'name' => 'accepted',
        'tags' => ['fast'],
        'note' => null,
    ];

    expect(fn () => testHydraType()->hydrate(AssertedRecord::class, [
        ...$valid,
        'note' => '',
    ]))->toThrow(AssertionException::class, "property 'note'")
        ->and(fn () => testHydraType()->hydrate(AssertedRecord::class, [
            ...$valid,
            'label' => '',
        ]))->toThrow(AssertionException::class, "property 'label'");
});

it('throws an inspectable assertion exception without exposing the rejected value', function () {
    $exception = null;
    try {
        testHydraType()->hydrate(AssertedRecord::class, [
            'name' => ' sensitive rejected value ',
            'tags' => [],
            'note' => null,
        ]);
    } catch (AssertionException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(AssertionException::class);
    if (!$exception instanceof AssertionException) {
        return;
    }

    expect($exception->targetClass())->toBe(AssertedRecord::class)
        ->and($exception->propertyName())->toBe('tags')
        ->and($exception->assertionClass())->toBe(NotEmpty::class)
        ->and($exception->reason())->toBe('Value must not be an empty string or array.')
        ->and($exception->getMessage())->not->toContain('sensitive rejected value');
});

it('supports assertions supplied by consumers', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, AssertedValue::class, ['value' => '  long enough  ']);
    $descriptor = new ClassDescriptor(AssertedValue::class, $configuration);
    $dependencies = (new ClassAnalyzer($descriptor))->getCacheDependencies();
    $writer = GeneratedHydratorInspector::closureBody(
        readGeneratedFile($descriptor->getHydratorFilePath()),
        'createCamelWriter',
    );

    expect($record->value())->toBe('long enough')
        ->and($dependencies)->toContain(MinimumLength::class)
        ->and(substr_count($writer, '$hydraAssertionValue ='))->toBe(1)
        ->and($writer)->toContain("\$hydraAssertionValue !== ''")
        ->toContain('strlen($hydraAssertionValue) >= 3')
        ->and(fn () => $hydra->hydrate(AssertedValue::class, ['value' => '  x  ']))
        ->toThrow(AssertionException::class, 'at least 3 bytes');
});

it('propagates assertion failures from nested hydration', function () {
    expect(fn () => testHydraType()->hydrate(AssertedAddress::class, [
        'country' => ['countryCode' => ''],
    ]))->toThrow(AssertionException::class, "property 'countryCode'");
});

it('stops a batch at its first assertion failure', function () {
    expect(fn () => testHydraType()->hydrateMany(AssertedValue::class, [
        ['value' => 'first'],
        ['value' => 'x'],
        ['value' => 'third'],
    ]))->toThrow(AssertionException::class, "property 'value'");
});
