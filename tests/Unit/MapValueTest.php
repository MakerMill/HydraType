<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Mutators\MapValue;
use MakerMill\HydraType\Tests\Fixtures\AccessLevel;
use MakerMill\HydraType\Tests\Fixtures\MappedValueRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('maps configured values before applying normal type conversion', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, MappedValueRecord::class, [
        'enabled' => 'enabled',
        'accessLevel' => 'administrator',
        'numericLabel' => '1',
        'identifier' => 'legacy',
    ]);
    $descriptor = new ClassDescriptor(MappedValueRecord::class, $configuration);
    $writer = GeneratedHydratorInspector::closureBody(
        readGeneratedFile($descriptor->getHydratorFilePath()),
        'createCamelWriter',
    );

    expect($record->values())->toBe([
        'enabled' => true,
        'accessLevel' => AccessLevel::Admin,
        'numericLabel' => 'first',
        'identifier' => 42,
    ])->and($writer)
        ->toContain('match ($hydraMutatorValue = ($data[\'enabled\'] ??')
        ->toContain('is_int($hydraMutatorValue = ($data[\'numericLabel\'] ??')
        ->toContain('[1 => "first", 2 => "second"][$hydraMutatorValue]')
        ->and(substr_count($writer, "\$data['enabled']"))->toBe(1)
        ->and(substr_count($writer, "\$data['numericLabel']"))->toBe(1);
});

it('passes non-keyable input through integer-key maps', function () {
    $mutator = new MapValue([1 => 'first']);
    $expression = $mutator->compile('$input');
    $map = eval('return static fn (mixed $input): mixed => ' . $expression . ';');
    if (!$map instanceof Closure) {
        throw new RuntimeException('Unable to compile value-map test expression.');
    }

    $input = ['unexpected'];

    expect($map($input))->toBe($input);
});

it('passes unmapped values to the normal type converters', function () {
    $record = hydrateObject(testHydraType(), MappedValueRecord::class, [
        'enabled' => 'yes',
        'accessLevel' => '2',
        'numericLabel' => 'unchanged',
        'identifier' => '7',
    ]);

    expect($record->values())->toBe([
        'enabled' => true,
        'accessLevel' => AccessLevel::Admin,
        'numericLabel' => 'unchanged',
        'identifier' => 7,
    ]);
});

it('rejects an empty value map', function () {
    expect(fn () => new MapValue([]))
        ->toThrow(InvalidArgumentException::class, 'Value map must not be empty.');
});

it('rejects non-scalar mapped values at runtime', function () {
    $reflection = new ReflectionClass(MapValue::class);

    expect(fn () => $reflection->newInstance(['nested' => []]))
        ->toThrow(InvalidArgumentException::class, 'Mapped values must be strings, integers, floats, or booleans.');
});
