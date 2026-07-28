<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\NamingConvention;
use MakerMill\HydraType\PropertyAnalyzer;
use MakerMill\HydraType\Tests\Fixtures\PlainRecord;
use MakerMill\HydraType\Tests\Fixtures\PrivateConstructorRecord;
use MakerMill\HydraType\Tests\Fixtures\ObjectShape\PublicRecord;
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
    $extractBody = GeneratedHydratorInspector::methodBody($source, 'extract');
    $extractManyBody = GeneratedHydratorInspector::methodBody($source, 'extractMany');
    $hydrateManyBody = GeneratedHydratorInspector::methodBody($source, 'hydrateMany');

    expect($record->values())->toBe(['id' => 42, 'displayName' => '123'])
        ->and($hydra->extract($record))->toBe(['id' => 42, 'displayName' => '123'])
        ->and($hydra->extract($record, NamingConvention::SnakeCase))->toBe([
            'id' => 42,
            'display_name' => '123',
        ])
        ->and(GeneratedHydratorInspector::closureBody($source, 'createCamelWriter'))->toBe(<<<'PHP'
$object->id = (int) ($data['id'] ?? (array_key_exists('id', $data) ? null : throw HydrationException::forMissingRequiredProperty(\MakerMill\HydraType\Tests\Fixtures\PlainRecord::class, 'id')));
$object->displayName = (string) ($data['displayName'] ?? (array_key_exists('displayName', $data) ? null : throw HydrationException::forMissingRequiredProperty(\MakerMill\HydraType\Tests\Fixtures\PlainRecord::class, 'displayName')));
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createSnakeWriter'))->toBe(<<<'PHP'
$object->id = (int) ($data['id'] ?? (array_key_exists('id', $data) ? null : throw HydrationException::forMissingRequiredProperty(\MakerMill\HydraType\Tests\Fixtures\PlainRecord::class, 'id')));
$object->displayName = (string) ($data['display_name'] ?? (array_key_exists('display_name', $data) ? null : throw HydrationException::forMissingRequiredProperty(\MakerMill\HydraType\Tests\Fixtures\PlainRecord::class, 'displayName')));
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createCamelReader'))->toBe(<<<'PHP'
return ['id' => $object->id, 'displayName' => $object->displayName];
PHP)
        ->and(GeneratedHydratorInspector::closureBody($source, 'createSnakeReader'))->toBe(<<<'PHP'
return ['id' => $object->id, 'display_name' => $object->displayName];
PHP)
        ->and(GeneratedHydratorInspector::methodBody($source, 'hydrate'))
        ->toContain('$object = new PlainRecord();')
        ->and($hydrateManyBody)->toContain('$object = new PlainRecord();')
        ->and($hydrateManyBody)->toContain('$firstData = $dataSet[array_key_first($dataSet)];')
        ->and($extractBody)
        ->toContain('return ($this->snakeReader ??= $this->createSnakeReader())($object);')
        ->and($extractBody)
        ->toContain('return ($this->camelReader ??= $this->createCamelReader())($object);')
        ->and($extractBody)->not->toContain('readerFor')
        ->and($extractManyBody)->toContain('$reader = $this->readerFor($namingConvention);')
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

it('inlines hydration for classes whose properties are publicly writable', function () {
    $configuration = testConfiguration();
    $hydra = new MakerMill\HydraType\HydraType($configuration);
    $camelRecord = hydrateObject($hydra, PublicRecord::class, [
        'id' => '1',
        'displayName' => 123,
        'active' => 'yes',
    ]);
    $snakeRecord = hydrateObject($hydra, PublicRecord::class, [
        'id' => '2',
        'display_name' => 'Snake',
        'active' => false,
    ]);
    $batch = hydrateObjects($hydra, PublicRecord::class, [
        ['id' => '3', 'display_name' => 'First', 'active' => 'true'],
        ['id' => '4', 'display_name' => 'Second', 'active' => 'false'],
    ]);
    $descriptor = new ClassDescriptor(PublicRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());
    $hydrateBody = GeneratedHydratorInspector::methodBody($source, 'hydrate');
    $hydrateManyBody = GeneratedHydratorInspector::methodBody($source, 'hydrateMany');

    expect($camelRecord->values())->toBe(['id' => 1, 'displayName' => '123', 'active' => true])
        ->and($snakeRecord->values())->toBe(['id' => 2, 'displayName' => 'Snake', 'active' => false])
        ->and($batch[0]->values())->toBe(['id' => 3, 'displayName' => 'First', 'active' => true])
        ->and($batch[1]->values())->toBe(['id' => 4, 'displayName' => 'Second', 'active' => false])
        ->and($hydrateBody)->toContain('$object->displayName = (string) ($data[\'display_name\'] ??')
        ->and($hydrateBody)->toContain('$object->displayName = (string) ($data[\'displayName\'] ??')
        ->and($hydrateBody)->toContain('if (array_key_exists(\'display_name\', $data))')
        ->and($hydrateBody)->not->toContain('$snakeCase =')
        ->and($hydrateManyBody)->toContain('$object->displayName = (string) ($data[\'display_name\'] ??')
        ->and($hydrateManyBody)->toContain('$object->displayName = (string) ($data[\'displayName\'] ??')
        ->and($hydrateManyBody)->toContain('$snakeCase = array_key_exists(\'display_name\', $firstData);')
        ->and($source)->not->toContain('private ?Closure $camelWriter')
        ->and($source)->not->toContain('createCamelWriter')
        ->and($source)->not->toContain('writerFor')
        ->and($source)->toContain('createCamelReader');
});

it('keeps asymmetric restricted setters on the scoped writer path when supported', function () {
    if (PHP_VERSION_ID < 80400) {
        expect(method_exists(ReflectionProperty::class, 'isPrivateSet'))->toBeFalse();
        return;
    }

    $property = eval(
        'namespace MakerMill\\HydraType\\Tests\\Fixtures\\ObjectShape; '
        . 'final class RestrictedWriteRecord { public private(set) int $id = 0; } '
        . 'return new \\ReflectionProperty(RestrictedWriteRecord::class, "id");'
    );
    if (!$property instanceof ReflectionProperty) {
        throw new RuntimeException('Unable to inspect the asymmetric-visibility fixture.');
    }

    expect((new PropertyAnalyzer($property))->isPubliclyWritable())->toBeFalse();
});
