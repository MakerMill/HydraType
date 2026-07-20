<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Consumer\MutatedRecord;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('compiles consumer mutators in reversible composition order', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $encodedSecret = base64_encode('HydraType');

    $record = hydrateObject($hydra, MutatedRecord::class, [
        'secret' => $encodedSecret,
        'note' => null,
    ]);
    $descriptor = new ClassDescriptor(MutatedRecord::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->values())->toBe([
        'secret' => 'epyTardyH',
        'note' => null,
        'label' => 'fallback',
    ])->and($hydra->extract($record))->toBe([
        'secret' => $encodedSecret,
        'note' => null,
        'label' => base64_encode('fallback'),
    ])->and(GeneratedHydratorInspector::closureBody($source, 'createCamelWriter'))
        ->toContain("strrev((string) base64_decode((string) \$data['secret'], true))")
        ->and(GeneratedHydratorInspector::closureBody($source, 'createCamelReader'))
        ->toContain('base64_encode((string) strrev((string) $object->secret))');
});

it('hydrates explicitly provided optional values through consumer mutators', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, MutatedRecord::class, [
        'secret' => base64_encode('secret'),
        'note' => base64_encode('note'),
        'label' => base64_encode('custom'),
    ]);

    expect($record->values())->toBe([
        'secret' => 'terces',
        'note' => 'note',
        'label' => 'custom',
    ]);
});
