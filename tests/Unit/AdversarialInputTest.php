<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Tests\Fixtures\JsonValueRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\NestedUser;
use MakerMill\HydraType\Tests\Fixtures\PlainRecord;

it('keeps code-shaped runtime input out of generated PHP', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $payload = <<<'PAYLOAD'
<?php throw new RuntimeException('HYDRA_INPUT_EXECUTED'); ?>
PAYLOAD;
    $hostileKey = "displayName']; throw new RuntimeException('HYDRA_KEY_EXECUTED'); //";

    $record = hydrateObject($hydra, PlainRecord::class, [
        'id' => '42',
        'displayName' => $payload,
        $hostileKey => 'ignored',
    ]);
    $descriptor = new ClassDescriptor(PlainRecord::class, $configuration);
    $generatedSource = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->values())->toBe([
        'id' => 42,
        'displayName' => $payload,
    ])->and(str_contains($generatedSource, 'HYDRA_INPUT_EXECUTED'))->toBeFalse()
        ->and(str_contains($generatedSource, 'HYDRA_KEY_EXECUTED'))->toBeFalse();
});

it('ignores mass-assignment and magic-property keys', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, PlainRecord::class, [
        'id' => 7,
        'displayName' => "safe\0value\nwith control bytes",
        '__construct' => ['id' => 999],
        '__destruct' => 'phpinfo',
        '__proto__' => ['isAdmin' => true],
        'constructor' => ['prototype' => ['isAdmin' => true]],
        'GLOBALS' => ['isAdmin' => true],
        'isAdmin' => true,
        "\0*\0displayName" => 'overridden',
    ]);

    expect($hydra->extract($record))->toBe([
        'id' => 7,
        'displayName' => "safe\0value\nwith control bytes",
    ]);
});

it('applies the same property allowlist at every nested level', function () {
    $hydra = testHydraType();
    $payload = "Robert'); DROP TABLE users;-- <script>alert(1)</script>";
    $record = hydrateObject($hydra, NestedUser::class, [
        'id' => 9,
        'primaryAddress' => [
            'streetName' => $payload,
            '__construct' => ['streetName' => 'overridden'],
            'country' => [
                'countryCode' => 'SE',
                '__proto__' => ['countryCode' => 'XX'],
                'GLOBALS' => ['countryCode' => 'XX'],
            ],
        ],
        'secondaryAddress' => null,
        'primaryAddress.country.countryCode' => 'XX',
    ]);

    expect($hydra->extract($record))->toBe([
        'id' => 9,
        'primaryAddress' => [
            'streetName' => $payload,
            'country' => ['countryCode' => 'SE'],
        ],
        'secondaryAddress' => null,
    ]);
});

it('treats object-pollution-shaped JSON members as ordinary data', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, JsonValueRecord::class, [
        'settings' => '{"__proto__":{"isAdmin":true},"constructor":{"prototype":{"role":"admin"}},"GLOBALS":"x"}',
        'metadata' => '{}',
        'optionalSettings' => null,
    ]);

    expect($record->values()['settings'])->toBe([
        '__proto__' => ['isAdmin' => true],
        'constructor' => ['prototype' => ['role' => 'admin']],
        'GLOBALS' => 'x',
    ]);
});

it('does not let hostile keys or values leak between batch rows', function () {
    $hydra = testHydraType();
    $records = hydrateObjects($hydra, PlainRecord::class, [
        [
            'id' => 1,
            'displayName' => "first'); exit; //",
            '__proto__' => ['id' => 99],
        ],
        [
            'id' => 2,
            'displayName' => 'second',
            'id OR 1=1' => 99,
        ],
    ]);

    expect($hydra->extractMany($records))->toBe([
        ['id' => 1, 'displayName' => "first'); exit; //"],
        ['id' => 2, 'displayName' => 'second'],
    ]);
});
