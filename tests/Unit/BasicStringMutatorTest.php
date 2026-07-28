<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Fixtures\StringNormalizedRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('compiles basic string normalization into the property assignments', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, StringNormalizedRecord::class, [
        'name' => "  HydraType\t",
        'path' => '///api/users///',
        'suffix' => "value \t\n",
        'reference' => 'REF... ',
        'description' => " \t\n",
    ]);
    $descriptor = new ClassDescriptor(StringNormalizedRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());
    $writer = GeneratedHydratorInspector::closureBody($source, 'createCamelWriter');

    expect($record->values())->toBe([
        'name' => 'HydraType',
        'path' => 'api/users',
        'suffix' => 'value',
        'reference' => 'REF',
        'description' => null,
    ])->and($writer)
        ->toContain('trim((string) ($data[\'name\'] ??')
        ->toContain('trim((string) ($data[\'path\'] ??')
        ->toContain('rtrim((string) ($data[\'suffix\'] ??')
        ->toContain('rtrim((string) ($data[\'reference\'] ??')
        ->toContain("\$hydraMutatorValue = (string) trim((string) \$data['description']")
        ->and(substr_count($writer, "trim((string) \$data['description']"))->toBe(1);
});

it('preserves normalized content and bypasses mutators for null', function () {
    $hydra = testHydraType();
    $content = hydrateObject($hydra, StringNormalizedRecord::class, [
        'name' => ' Name ',
        'path' => '/path/',
        'suffix' => 'suffix ',
        'reference' => 'REF. ',
        'description' => '  useful content  ',
    ]);
    $null = hydrateObject($hydra, StringNormalizedRecord::class, [
        'name' => 'Name',
        'path' => 'path',
        'suffix' => 'suffix',
        'reference' => 'REF',
        'description' => null,
    ]);

    expect($content->values()['description'])->toBe('useful content')
        ->and($null->values()['description'])->toBeNull();
});
