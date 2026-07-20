<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Fixtures\JsonValueRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('hydrates and extracts JSON values with independent flags', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, JsonValueRecord::class, [
        'settings' => '{"endpoint":"https:\/\/example.com\/api"}',
        'metadata' => '{"id":9223372036854775808}',
        'optionalSettings' => null,
    ]);
    $descriptor = new ClassDescriptor(JsonValueRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());
    $writer = GeneratedHydratorInspector::closureBody($source, 'createCamelWriter');
    $reader = GeneratedHydratorInspector::closureBody($source, 'createCamelReader');

    expect($record->values())->toEqual([
        'settings' => ['endpoint' => 'https://example.com/api'],
        'metadata' => (object) ['id' => '9223372036854775808'],
        'optionalSettings' => null,
    ])->and($hydra->extract($record))->toBe([
        'settings' => '{"endpoint":"https://example.com/api"}',
        'metadata' => '{"id":"9223372036854775808"}',
        'optionalSettings' => null,
    ])->and($writer)
        ->toContain('json_decode((string) $data[\'settings\'], true, 512, \\JSON_THROW_ON_ERROR)')
        ->toContain('json_decode((string) $data[\'metadata\'], false, 64, \\JSON_THROW_ON_ERROR | 2)')
        ->and($reader)
        ->toContain('json_encode($object->settings, \\JSON_THROW_ON_ERROR | 64, 512)')
        ->toContain('json_encode($object->metadata, \\JSON_THROW_ON_ERROR, 64)')
        ->toContain(
            'isset($object->optionalSettings) ? '
            . 'json_encode($object->optionalSettings, \\JSON_THROW_ON_ERROR, 512) : null',
        );
});

it('round trips non-null JSON values', function () {
    $hydra = testHydraType();
    $input = [
        'settings' => '{"enabled":true}',
        'metadata' => '{"source":"api"}',
        'optionalSettings' => '{"mode":"strict"}',
    ];
    $record = hydrateObject($hydra, JsonValueRecord::class, $input);

    expect($hydra->extract($record))->toBe($input);
});

it('surfaces JSON decoding and encoding failures', function () {
    $hydra = testHydraType();
    $validData = [
        'settings' => '{}',
        'metadata' => '{}',
        'optionalSettings' => null,
    ];

    expect(fn () => $hydra->hydrate(JsonValueRecord::class, [
        ...$validData,
        'settings' => '{invalid',
    ]))->toThrow(JsonException::class);

    $invalidUtf8 = hydrateObject($hydra, JsonValueRecord::class, $validData);
    $settings = new ReflectionProperty($invalidUtf8, 'settings');
    $settings->setValue($invalidUtf8, ['value' => "\xB1\x31"]);

    expect(fn () => $hydra->extract($invalidUtf8))->toThrow(JsonException::class);
});
