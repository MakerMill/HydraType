<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Fixtures\CaseNormalizedRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('compiles case normalization directly into property assignments', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, CaseNormalizedRecord::class, [
        'email' => '  USER@EXAMPLE.COM  ',
        'countryCode' => 'se',
        'alias' => 'MixedCase',
    ]);
    $descriptor = new ClassDescriptor(CaseNormalizedRecord::class, $configuration);
    $writer = GeneratedHydratorInspector::closureBody(
        readGeneratedFile($descriptor->getHydratorFilePath()),
        'createCamelWriter',
    );

    expect($record->values())->toBe([
        'email' => 'user@example.com',
        'countryCode' => 'SE',
        'alias' => 'mixedcase',
    ])->and($writer)
        ->toContain('strtolower((string) trim((string) $data[\'email\']')
        ->toContain('strtoupper((string) $data[\'countryCode\'])');
});

it('bypasses case normalization for nullable input', function () {
    $record = hydrateObject(testHydraType(), CaseNormalizedRecord::class, [
        'email' => 'USER@EXAMPLE.COM',
        'countryCode' => 'se',
        'alias' => null,
    ]);

    expect($record->values()['alias'])->toBeNull();
});
