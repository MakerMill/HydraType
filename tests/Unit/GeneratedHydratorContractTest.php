<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\NamingConvention;
use MakerMill\HydraType\Tests\Fixtures\PlainRecord;
use MakerMill\HydraType\Tests\Fixtures\PrivateConstructorRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('keeps the unselected generated property path minimal', function () {
    $configuration = testConfiguration();
    $hydra = new MakerMill\HydraType\HydraType($configuration);
    $record = hydrateObject($hydra, PlainRecord::class, [
        'id' => '42',
        'displayName' => 123,
    ]);
    $descriptor = new ClassDescriptor(PlainRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->values())->toBe(['id' => 42, 'displayName' => '123'])
        ->and($hydra->extract($record))->toBe(['id' => 42, 'displayName' => '123'])
        ->and($hydra->extract($record, NamingConvention::SnakeCase))->toBe([
            'id' => 42,
            'display_name' => '123',
        ])
        ->and(GeneratedHydratorInspector::closureBody($source, 'createCamelWriter'))->toBe(<<<'PHP'
$object->id = (int) $data['id'];
$object->displayName = (string) $data['displayName'];
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createSnakeWriter'))->toBe(<<<'PHP'
$object->id = (int) $data['id'];
$object->displayName = (string) $data['display_name'];
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createCamelReader'))->toBe(<<<'PHP'
return ['id' => $object->id, 'displayName' => $object->displayName];
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createSnakeReader'))->toBe(<<<'PHP'
return ['id' => $object->id, 'display_name' => $object->displayName];
PHP)
        ->and(GeneratedHydratorInspector::methodBody($source, 'hydrate'))
        ->toContain('$object = new PlainRecord();')
        ->and(GeneratedHydratorInspector::methodBody($source, 'hydrateMany'))
        ->toContain('$object = new PlainRecord();')
        ->and($source)->toContain('/** @implements HydratorInterface<PlainRecord> */')
        ->and($source)->toContain('/** @return Closure(PlainRecord): array<string, mixed> */')
        ->and(str_contains($source, 'ReflectionClass'))->toBeFalse()
        ->and(str_contains($source, 'FactoryAwareHydratorInterface'))->toBeFalse()
        ->and(str_contains($source, 'HydratorFactory'))->toBeFalse()
        ->and(str_contains($source, 'nestedHydrator'))->toBeFalse()
        ->and(str_contains($source, 'hydraAssertionValue'))->toBeFalse()
        ->and(str_contains($source, 'AssertionException'))->toBeFalse();
});

it('retains cached reflection when a constructor must be bypassed', function () {
    $configuration = testConfiguration();
    $hydra = new MakerMill\HydraType\HydraType($configuration);
    $record = hydrateObject($hydra, PrivateConstructorRecord::class, [
        'id' => 1,
    ]);
    $descriptor = new ClassDescriptor(PrivateConstructorRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->id())->toBe(1)
        ->and($source)->toContain('private ReflectionClass $reflectionClass;')
        ->and(GeneratedHydratorInspector::methodBody($source, 'hydrate'))
        ->toContain('$object = $this->reflectionClass->newInstanceWithoutConstructor();')
        ->and(GeneratedHydratorInspector::methodBody($source, 'hydrateMany'))
        ->toContain('$object = $this->reflectionClass->newInstanceWithoutConstructor();');
});
