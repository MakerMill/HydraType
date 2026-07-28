<?php

declare(strict_types=1);

use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\NamingConvention;
use MakerMill\HydraType\Tests\Fixtures\AccessLevel;
use MakerMill\HydraType\Tests\Fixtures\AmbiguousNamedRecord;
use MakerMill\HydraType\Tests\Fixtures\DateTimeRecord;
use MakerMill\HydraType\Tests\Fixtures\EmptyInputRecord;
use MakerMill\HydraType\Tests\Fixtures\JsonDecodedRecord;
use MakerMill\HydraType\Tests\Fixtures\LeftTrimmedRecord;
use MakerMill\HydraType\Tests\Fixtures\NullableRecord;
use MakerMill\HydraType\Tests\Fixtures\OptionalRecord;
use MakerMill\HydraType\Tests\Fixtures\RecordState;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;
use MakerMill\HydraType\Tests\Fixtures\SnakeNamedRecord;
use MakerMill\HydraType\Tests\Fixtures\TypedRecord;
use MakerMill\HydraType\Tests\Fixtures\TypeConvertedRecord;
use MakerMill\HydraType\Tests\Fixtures\UnsupportedClassRecord;

it('hydrates an object without requiring a contract', function () {
    $hydra = testHydraType();

    $user = hydrateObject($hydra, SimpleUser::class, [
        'id' => '1',
        'user_name' => 'John Doe',
        'password' => 'password',
    ]);

    expect($user)->toBeInstanceOf(SimpleUser::class)
        ->and($user->getId())->toBe(1)
        ->and($user->getUserName())->toBe('John Doe');
});

it('hydrates multiple objects without requiring a contract', function () {
    $hydra = testHydraType();

    $users = hydrateObjects($hydra, SimpleUser::class, [
        ['id' => 1, 'userName' => 'John Doe', 'password' => 'password'],
        ['id' => 2, 'userName' => 'Jane Doe', 'password' => 'password'],
    ]);

    expect($users)->toHaveCount(2)
        ->and($users[0])->toBeInstanceOf(SimpleUser::class)
        ->and($users[1])->toBeInstanceOf(SimpleUser::class);
});

it('extracts an object using camel case by default', function () {
    $record = new TypedRecord(1, 'Camel Case', true, RecordState::Active);

    expect(testHydraType()->extract($record))->toBe([
        'id' => 1,
        'displayName' => 'Camel Case',
        'enabled' => true,
        'state' => 'active',
    ]);
});

it('extracts an object using snake case', function () {
    $record = new TypedRecord(2, 'Snake Case', false, RecordState::Archived);

    expect(testHydraType()->extract($record, NamingConvention::SnakeCase))->toBe([
        'id' => 2,
        'display_name' => 'Snake Case',
        'enabled' => false,
        'state' => 'archived',
    ]);
});

it('extracts multiple objects with one naming convention', function () {
    $records = [
        5 => new TypedRecord(1, 'First', true, RecordState::Active),
        9 => new TypedRecord(2, 'Second', false, RecordState::Archived),
    ];
    $hydra = testHydraType();

    expect($hydra->extractMany($records, NamingConvention::SnakeCase))->toBe([
        [
            'id' => 1,
            'display_name' => 'First',
            'enabled' => true,
            'state' => 'active',
        ],
        [
            'id' => 2,
            'display_name' => 'Second',
            'enabled' => false,
            'state' => 'archived',
        ],
    ])->and($hydra->extractMany([], NamingConvention::SnakeCase))->toBe([]);
});

it('reports invalid input through the public interface', function () {
    $hydra = testHydraType();

    expect(fn () => $hydra->hydrate(SimpleUser::class, []))
        ->toThrow(HydrationException::class);
});

it('keeps the most recently resolved hydrator on the facade fast path', function () {
    $hydra = testHydraType();
    $reflection = new ReflectionClass($hydra);
    $lastClassName = $reflection->getProperty('lastClassName');
    $lastHydrator = $reflection->getProperty('lastHydrator');

    $simpleUserHydrator = $hydra->hydrator(SimpleUser::class);

    expect($lastClassName->getValue($hydra))->toBe(SimpleUser::class)
        ->and($lastHydrator->getValue($hydra))->toBe($simpleUserHydrator)
        ->and($hydra->hydrator(SimpleUser::class))->toBe($simpleUserHydrator);

    $typedRecordHydrator = $hydra->hydrator(TypedRecord::class);

    expect($lastClassName->getValue($hydra))->toBe(TypedRecord::class)
        ->and($lastHydrator->getValue($hydra))->toBe($typedRecordHydrator)
        ->and($hydra->hydrator(SimpleUser::class))->toBe($simpleUserHydrator)
        ->and($lastClassName->getValue($hydra))->toBe(SimpleUser::class);
});

it('preserves the original assignment failure with accurate hydration context', function (bool $batch) {
    $data = [
        'id' => 1,
        'displayName' => 'Invalid state',
        'enabled' => true,
        'state' => 'not-a-state',
    ];

    $exception = null;
    try {
        $batch
            ? testHydraType()->hydrateMany(TypedRecord::class, [$data])
            : testHydraType()->hydrate(TypedRecord::class, $data);
    } catch (HydrationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(HydrationException::class)
        ->and($exception?->getMessage())->toContain("Unable to hydrate class '" . TypedRecord::class . "'")
        ->and($exception?->getMessage())->toContain('TypedRecord::$state')
        ->and($exception?->getPrevious())->toBeInstanceOf(TypeError::class);
})->with([
    'single object' => [false],
    'object batch' => [true],
]);

it('selects camel case and snake case writers for the same class', function () {
    $hydra = testHydraType();

    $snakeRecord = hydrateObject($hydra, TypedRecord::class, [
        'id' => '1',
        'display_name' => 'Snake Case',
        'enabled' => 'Y',
        'state' => 'active',
    ]);
    $camelRecord = hydrateObject($hydra, TypedRecord::class, [
        'id' => '2',
        'displayName' => 'Camel Case',
        'enabled' => true,
        'state' => 'archived',
    ]);

    expect($snakeRecord)->toBeInstanceOf(TypedRecord::class)
        ->and($snakeRecord->getId())->toBe(1)
        ->and($snakeRecord->getDisplayName())->toBe('Snake Case')
        ->and($snakeRecord->isEnabled())->toBeTrue()
        ->and($snakeRecord->getState())->toBe(RecordState::Active)
        ->and($camelRecord)->toBeInstanceOf(TypedRecord::class)
        ->and($camelRecord->getId())->toBe(2)
        ->and($camelRecord->getDisplayName())->toBe('Camel Case')
        ->and($camelRecord->isEnabled())->toBeTrue()
        ->and($camelRecord->getState())->toBe(RecordState::Archived);
});

it('selects the snake case writer once for a batch', function () {
    $hydra = testHydraType();

    $records = hydrateObjects($hydra, TypedRecord::class, [
        ['id' => 1, 'display_name' => 'First', 'enabled' => '1', 'state' => 'active'],
        ['id' => 2, 'display_name' => 'Second', 'enabled' => 't', 'state' => 'archived'],
    ]);

    expect($records)->toHaveCount(2)
        ->and($records[0]->getDisplayName())->toBe('First')
        ->and($records[1]->getDisplayName())->toBe('Second')
        ->and($records[0]->isEnabled())->toBeTrue()
        ->and($records[1]->getState())->toBe(RecordState::Archived);
});

it('supports camel case and snake case input for a snake case property', function () {
    $hydra = testHydraType();

    $camelRecord = hydrateObject($hydra, SnakeNamedRecord::class, ['displayName' => 'Camel Case']);
    $snakeRecord = hydrateObject($hydra, SnakeNamedRecord::class, ['display_name' => 'Snake Case']);

    expect($camelRecord->getDisplayName())->toBe('Camel Case')
        ->and($snakeRecord->getDisplayName())->toBe('Snake Case');
});

it('rejects property names that map to the same input key', function () {
    $hydra = testHydraType();

    expect(fn () => $hydra->hydrate(AmbiguousNamedRecord::class, ['displayName' => 'Ambiguous']))
        ->toThrow(HydrationException::class, "Input key 'displayName' maps to both");
});

it('compiles inferred type converters for supported property types', function () {
    $hydra = testHydraType();
    $payload = ['source' => 'payload'];
    $untyped = new stdClass();

    $record = hydrateObject($hydra, TypeConvertedRecord::class, [
        'id' => '42',
        'score' => '12.5',
        'name' => 123,
        'enabled' => 'yes',
        'tags' => 'tag',
        'settings' => ['theme' => 'dark'],
        'payload' => $payload,
        'untyped' => $untyped,
        'accessLevel' => '2',
    ]);

    $values = $record->values();

    expect($values['id'])->toBe(42)
        ->and($values['score'])->toBe(12.5)
        ->and($values['name'])->toBe('123')
        ->and($values['enabled'])->toBeTrue()
        ->and($values['tags'])->toBe(['tag'])
        ->and($values['settings'])->toEqual((object) ['theme' => 'dark'])
        ->and($values['payload'])->toBe($payload)
        ->and($values['untyped'])->toBe($untyped)
        ->and($values['accessLevel'])->toBe(AccessLevel::Admin)
        ->and($hydra->extract($record)['accessLevel'])->toBe(2)
        ->and(TypeConvertedRecord::metadata())->toBe('unchanged');
});

it('compiles explicit mutators before inferred type conversion', function () {
    $configuration = testConfiguration();
    $record = hydrateObject(
        new HydraType($configuration),
        LeftTrimmedRecord::class,
        ['name' => '   HydraType   '],
    );
    $descriptor = new ClassDescriptor(LeftTrimmedRecord::class, $configuration);
    $generatedCode = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->getName())->toBe('HydraType   ');
    expect($generatedCode)->toContain('ltrim((string) $data[\'name\'], " \\t\\n\\r\\x00\\v")');
});

it('requires an explicit mutator for unsupported property types', function () {
    $hydra = testHydraType();

    expect(fn () => $hydra->hydrate(UnsupportedClassRecord::class, ['createdAt' => '2026-07-18']))
        ->toThrow(HydrationException::class, "unsupported type 'DateTimeImmutable' and no mutator produces that type");
});

it('hydrates a batch whose integer keys do not start at zero', function () {
    $records = hydrateObjects(testHydraType(), TypedRecord::class, [
        5 => ['id' => 1, 'displayName' => 'Keyed', 'enabled' => true, 'state' => 'active'],
    ]);

    expect($records)->toHaveCount(1)
        ->and($records[0]->getDisplayName())->toBe('Keyed');
});

it('preserves null and missing values for nullable properties', function () {
    $hydra = testHydraType();

    $nullRecord = hydrateObject($hydra, NullableRecord::class, [
        'id' => 1,
        'displayName' => null,
        'recordState' => null,
    ]);
    $snakeRecord = hydrateObject($hydra, NullableRecord::class, [
        'id' => 2,
        'display_name' => '  Snake name',
        'record_state' => 'active',
    ]);

    expect($nullRecord->values())->toBe([
        'id' => 1,
        'displayName' => null,
        'recordState' => null,
        'payload' => null,
        'untyped' => null,
    ])->and($hydra->extract($nullRecord))->toBe([
        'id' => 1,
        'displayName' => null,
        'recordState' => null,
        'payload' => null,
        'untyped' => null,
    ])->and($snakeRecord->values())->toBe([
        'id' => 2,
        'displayName' => 'Snake name',
        'recordState' => RecordState::Active,
        'payload' => null,
        'untyped' => null,
    ])->and($hydra->extract($snakeRecord, NamingConvention::SnakeCase))->toBe([
        'id' => 2,
        'display_name' => 'Snake name',
        'record_state' => 'active',
        'payload' => null,
        'untyped' => null,
    ]);
});

it('preserves property defaults when optional input is missing', function () {
    $hydra = testHydraType();

    $defaultRecord = hydrateObject($hydra, OptionalRecord::class, ['id' => 1]);
    $snakeRecord = hydrateObject($hydra, OptionalRecord::class, [
        'id' => 2,
        'display_label' => 'Provided label',
    ]);

    expect($defaultRecord->getId())->toBe(1)
        ->and($defaultRecord->getDisplayLabel())->toBe('Default label')
        ->and($snakeRecord->getId())->toBe(2)
        ->and($snakeRecord->getDisplayLabel())->toBe('Provided label');
});

it('accepts empty input when every property is nullable or optional', function () {
    $record = hydrateObject(testHydraType(), EmptyInputRecord::class, []);

    expect($record->values())->toBe([
        'name' => null,
        'count' => 1,
    ]);
});

it('compiles JSON decoding for array, object, flags, and nullable output', function () {
    $configuration = testConfiguration();
    $record = hydrateObject(new HydraType($configuration), JsonDecodedRecord::class, [
        'settings' => '{"theme":"dark","items":[1]}',
        'metadata' => '{"source":"api"}',
        'largeNumber' => '{"id":9223372036854775808}',
        'optionalSettings' => null,
    ]);
    $descriptor = new ClassDescriptor(JsonDecodedRecord::class, $configuration);
    $generatedCode = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record->values())->toEqual([
        'settings' => ['theme' => 'dark', 'items' => [1]],
        'metadata' => (object)['source' => 'api'],
        'largeNumber' => ['id' => '9223372036854775808'],
        'optionalSettings' => null,
    ])->and($generatedCode)
        ->toContain('json_decode((string) $data[\'settings\'], true, 512, \\JSON_THROW_ON_ERROR)')
        ->toContain('json_decode((string) $data[\'metadata\'], false, 64, \\JSON_THROW_ON_ERROR)')
        ->toContain('json_decode((string) $data[\'largeNumber\'], true, 512, \\JSON_THROW_ON_ERROR | 2)')
        ->and(str_contains($generatedCode, '$object->settings = (array)'))->toBeFalse();
});

it('surfaces JSON decoding failures', function () {
    $data = [
        'settings' => '{invalid',
        'metadata' => '{}',
        'largeNumber' => '{}',
        'optionalSettings' => null,
    ];

    expect(fn () => testHydraType()->hydrate(JsonDecodedRecord::class, $data))
        ->toThrow(JsonException::class);
});

it('compiles date-time hydration and extraction', function () {
    $configuration = testConfiguration();
    $hydra = new HydraType($configuration);
    $record = hydrateObject($hydra, DateTimeRecord::class, [
        'createdAt' => '2026-07-18 14:30:45 Europe/Stockholm',
        'publishedAt' => null,
    ]);
    $descriptor = new ClassDescriptor(DateTimeRecord::class, $configuration);
    $generatedCode = readGeneratedFile($descriptor->getHydratorFilePath());

    expect($record)->toBeInstanceOf(DateTimeRecord::class)
        ->and($record->getCreatedAt()->format('Y-m-d H:i:s'))->toBe('2026-07-18 14:30:45')
        ->and($record->getPublishedAt())->toBeNull()
        ->and($hydra->extract($record))->toBe([
            'createdAt' => '2026-07-18 14:30:45',
            'publishedAt' => null,
        ])->and($generatedCode)
        ->toContain('new \\DateTimeImmutable((string) $data[\'createdAt\'])')
        ->toContain('$object->createdAt->format("Y-m-d H:i:s")')
        ->toContain('isset($object->publishedAt) ? $object->publishedAt->format("Y-m-d\\\\TH:i:sP") : null');
});

it('hydrates and extracts nullable dates in snake case batches', function () {
    $hydra = testHydraType();
    $records = hydrateObjects($hydra, DateTimeRecord::class, [
        [
            'created_at' => '2026-07-18 10:00:00 UTC',
            'published_at' => '2026-07-18T12:00:00+00:00',
        ],
        [
            'created_at' => '2026-07-19 11:00:00 UTC',
            'published_at' => null,
        ],
    ]);

    expect($hydra->extractMany($records, NamingConvention::SnakeCase))->toBe([
        [
            'created_at' => '2026-07-18 10:00:00',
            'published_at' => '2026-07-18T12:00:00+00:00',
        ],
        [
            'created_at' => '2026-07-19 11:00:00',
            'published_at' => null,
        ],
    ]);
});

it('creates naming writers lazily and caches them after first use', function () {
    $hydrator = testConfiguration()->getHydratorFactory()->create(TypedRecord::class);
    $reflection = new ReflectionClass($hydrator);
    $camelWriter = $reflection->getProperty('camelWriter');
    $snakeWriter = $reflection->getProperty('snakeWriter');

    expect($camelWriter->getValue($hydrator))->toBeNull()
        ->and($snakeWriter->getValue($hydrator))->toBeNull();

    $hydrator->hydrate([
        'id' => 1,
        'displayName' => 'Camel',
        'enabled' => true,
        'state' => 'active',
    ]);

    expect($camelWriter->getValue($hydrator))->toBeInstanceOf(Closure::class)
        ->and($snakeWriter->getValue($hydrator))->toBeNull();

    $hydrator->hydrate([
        'id' => 2,
        'display_name' => 'Snake',
        'enabled' => true,
        'state' => 'archived',
    ]);

    expect($camelWriter->getValue($hydrator))->toBeInstanceOf(Closure::class)
        ->and($snakeWriter->getValue($hydrator))->toBeInstanceOf(Closure::class);
});

it('creates naming readers lazily and caches them after first use', function () {
    $hydrator = testConfiguration()->getHydratorFactory()->create(TypedRecord::class);
    $reflection = new ReflectionClass($hydrator);
    $camelReader = $reflection->getProperty('camelReader');
    $snakeReader = $reflection->getProperty('snakeReader');
    $record = new TypedRecord(1, 'Reader', true, RecordState::Active);

    expect($camelReader->getValue($hydrator))->toBeNull()
        ->and($snakeReader->getValue($hydrator))->toBeNull();

    $hydrator->extract($record);

    expect($camelReader->getValue($hydrator))->toBeInstanceOf(Closure::class)
        ->and($snakeReader->getValue($hydrator))->toBeNull();

    $hydrator->extract($record, NamingConvention::SnakeCase);

    expect($camelReader->getValue($hydrator))->toBeInstanceOf(Closure::class)
        ->and($snakeReader->getValue($hydrator))->toBeInstanceOf(Closure::class);
});
