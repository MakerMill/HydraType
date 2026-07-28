<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Mutators\StringReplace;
use MakerMill\HydraType\Tests\Fixtures\StringReplacedRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('compiles repeated string replacements in declaration order', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, StringReplacedRecord::class, [
        'slug' => '  Hydra  Type  ',
        'path' => 'api\\users\\42',
        'alias' => 'old-value',
    ]);
    $descriptor = new ClassDescriptor(StringReplacedRecord::class, $configuration);
    $writer = GeneratedHydratorInspector::closureBody(
        readGeneratedFile($descriptor->getHydratorFilePath()),
        'createCamelWriter',
    );

    expect($record->values())->toBe([
        'slug' => 'Hydra-Type',
        'path' => 'api/users/42',
        'alias' => 'new-value',
    ])->and($writer)
        ->toContain(
            'str_replace("--", "-", (string) str_replace(" ", "-", (string) trim((string) ($data[\'slug\'] ??',
        );
});

it('bypasses string replacement for nullable input', function () {
    $record = hydrateObject(testHydraType(), StringReplacedRecord::class, [
        'slug' => 'Hydra Type',
        'path' => 'api\\users',
        'alias' => null,
    ]);

    expect($record->values()['alias'])->toBeNull();
});

it('rejects an empty replacement search', function () {
    expect(fn () => new StringReplace('', 'value'))
        ->toThrow(InvalidArgumentException::class, 'String replacement search must not be empty.');
});
