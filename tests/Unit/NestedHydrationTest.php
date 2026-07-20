<?php

declare(strict_types=1);

use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\HydratorCache;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\NamingConvention;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\AbstractTargetRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\Address;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\CardPayment;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\Country;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\CycleA;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\CycleB;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\ExplicitPaymentRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\ImplicitAbstractRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\ImplicitInterfaceRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\InternalTargetRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\InvalidPaymentRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\JsonPaymentRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\MissingTargetRecord;
use MakerMill\HydraType\Tests\Fixtures\NestedHydration\NestedUser;
use MakerMill\HydraType\Tests\Support\GeneratedHydratorInspector;

it('hydrates and extracts concrete nested objects recursively', function () {
    $hydra = testHydraType();
    $user = hydrateObject($hydra, NestedUser::class, [
        'id' => '42',
        'primaryAddress' => [
            'streetName' => 'Main Street',
            'country' => ['countryCode' => 'SE'],
        ],
        'secondaryAddress' => null,
    ]);

    expect($user->id())->toBe(42)
        ->and($user->primaryAddress())->toBeInstanceOf(Address::class)
        ->and($user->primaryAddress()->streetName())->toBe('Main Street')
        ->and($user->primaryAddress()->country())->toBeInstanceOf(Country::class)
        ->and($user->primaryAddress()->country()->countryCode())->toBe('SE')
        ->and($user->secondaryAddress())->toBeNull()
        ->and($hydra->extract($user))->toBe([
            'id' => 42,
            'primaryAddress' => [
                'streetName' => 'Main Street',
                'country' => ['countryCode' => 'SE'],
            ],
            'secondaryAddress' => null,
        ])
        ->and($hydra->extract($user, NamingConvention::SnakeCase))->toBe([
            'id' => 42,
            'primary_address' => [
                'street_name' => 'Main Street',
                'country' => ['country_code' => 'SE'],
            ],
            'secondary_address' => null,
        ]);
});

it('detects naming conventions independently at every nested level', function () {
    $user = hydrateObject(testHydraType(), NestedUser::class, [
        'id' => 7,
        'primary_address' => [
            'street_name' => 'Nested Road',
            'country' => ['country_code' => 'NO'],
        ],
        'secondary_address' => null,
    ]);

    expect($user->primaryAddress()->streetName())->toBe('Nested Road')
        ->and($user->primaryAddress()->country()->countryCode())->toBe('NO');
});

it('passes through values that already have the nested target type', function () {
    $address = new Address('Existing Street', new Country('DK'));
    $user = hydrateObject(testHydraType(), NestedUser::class, [
        'id' => 1,
        'primaryAddress' => $address,
        'secondaryAddress' => $address,
    ]);

    expect($user->primaryAddress())->toBe($address)
        ->and($user->secondaryAddress())->toBe($address);
});

it('hydrates and extracts nested batches with one selected naming path', function () {
    $hydra = testHydraType();
    $users = hydrateObjects($hydra, NestedUser::class, [
        [
            'id' => 1,
            'primary_address' => [
                'street_name' => 'First Street',
                'country' => ['country_code' => 'SE'],
            ],
            'secondary_address' => null,
        ],
        [
            'id' => 2,
            'primary_address' => [
                'street_name' => 'Second Street',
                'country' => ['country_code' => 'NO'],
            ],
            'secondary_address' => null,
        ],
    ]);

    expect($users[0]->primaryAddress()->country()->countryCode())->toBe('SE')
        ->and($users[1]->primaryAddress()->streetName())->toBe('Second Street')
        ->and($hydra->extractMany($users, NamingConvention::SnakeCase))->toBe([
            [
                'id' => 1,
                'primary_address' => [
                    'street_name' => 'First Street',
                    'country' => ['country_code' => 'SE'],
                ],
                'secondary_address' => null,
            ],
            [
                'id' => 2,
                'primary_address' => [
                    'street_name' => 'Second Street',
                    'country' => ['country_code' => 'NO'],
                ],
                'secondary_address' => null,
            ],
        ]);
});

it('uses an explicit concrete target for interface properties', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, ExplicitPaymentRecord::class, [
        'payment' => ['reference' => 'card-1234'],
    ]);

    expect($record->payment())->toBeInstanceOf(CardPayment::class)
        ->and($record->payment()->reference())->toBe('card-1234')
        ->and($hydra->extract($record))->toBe([
            'payment' => ['reference' => 'card-1234'],
        ]);
});

it('composes explicit nested hydration with bidirectional mutators', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, JsonPaymentRecord::class, [
        'payment' => '{"reference":"json-card"}',
    ]);

    expect($record->payment())->toBeInstanceOf(CardPayment::class)
        ->and($record->payment()->reference())->toBe('json-card')
        ->and($hydra->extract($record))->toBe([
            'payment' => '{"reference":"json-card"}',
        ]);
});

it('rejects explicit targets that cannot be assigned to the property', function () {
    expect(fn () => testHydraType()->hydrator(InvalidPaymentRecord::class))
        ->toThrow(HydrationException::class, 'is not assignable');
});

it('explains how to select targets for non-concrete property types', function (string $className) {
    if (!class_exists($className)) {
        throw new RuntimeException("Missing nested hydration fixture '{$className}'.");
    }
    expect(fn () => testHydraType()->hydrator($className))
        ->toThrow(HydrationException::class, 'Select a concrete user-defined class with #[HydrateAs(...)]');
})->with([
    ImplicitInterfaceRecord::class,
    ImplicitAbstractRecord::class,
]);

it('rejects missing, abstract, and internal explicit targets', function (string $className) {
    if (!class_exists($className)) {
        throw new RuntimeException("Missing nested hydration fixture '{$className}'.");
    }
    expect(fn () => testHydraType()->hydrator($className))
        ->toThrow(HydrationException::class, 'must be a concrete user-defined class');
})->with([
    MissingTargetRecord::class,
    AbstractTargetRecord::class,
    InternalTargetRecord::class,
]);

it('resolves one child hydrator outside the generated hot path', function () {
    $configuration = testConfiguration();
    hydrateObject(new HydraType($configuration), NestedUser::class, [
        'id' => 1,
        'primaryAddress' => [
            'streetName' => 'Compiled Street',
            'country' => ['countryCode' => 'FI'],
        ],
        'secondaryAddress' => null,
    ]);
    $descriptor = new ClassDescriptor(NestedUser::class, $configuration);
    $source = readGeneratedFile($descriptor->getHydratorFilePath());
    $writerMethod = GeneratedHydratorInspector::methodBody($source, 'createCamelWriter');
    $writerClosure = GeneratedHydratorInspector::closureBody($source, 'createCamelWriter');

    expect(substr_count($source, 'private ?HydratorInterface $nestedHydrator'))->toBe(1)
        ->and($writerMethod)->toContain(
            '$nestedHydrator0 = $this->nestedHydrator0 ??= '
            . '$this->hydratorFactory->create('
            . '\\MakerMill\\HydraType\\Tests\\Fixtures\\NestedHydration\\Address::class);',
        )
        ->and($writerClosure)->not->toContain('hydratorFactory')
        ->and($writerClosure)->toContain('$nestedHydrator0->hydrate((array) $hydraNestedValue)');
});

it('warms complete nested graphs and terminates on class cycles', function () {
    $namespace = 'MakerMill\\HydraType\\Tests\\NestedWarmup' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/nested-warmup-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $files = (new HydratorCache($configuration))->warm(
        NestedUser::class,
        CycleA::class,
        Address::class,
        CycleB::class,
        NestedUser::class,
    );

    expect(array_keys($files))->toBe([
        NestedUser::class,
        CycleA::class,
        Address::class,
        CycleB::class,
        Country::class,
    ]);

    $readOnly = new HydraType(new Configuration($namespace, $directory, CacheMode::ReadOnly));
    $cycle = hydrateObject($readOnly, CycleA::class, [
        'cycleB' => ['cycleA' => null],
    ]);

    expect($cycle->cycleB())->toBeInstanceOf(CycleB::class)
        ->and($cycle->cycleB()?->cycleA())->toBeNull();
});
