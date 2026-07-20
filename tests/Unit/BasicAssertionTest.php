<?php

declare(strict_types=1);

use MakerMill\HydraType\Assertions\Between;
use MakerMill\HydraType\Assertions\Contains;
use MakerMill\HydraType\Assertions\EndsWith;
use MakerMill\HydraType\Assertions\Equal;
use MakerMill\HydraType\Assertions\GreaterThan;
use MakerMill\HydraType\Assertions\GreaterThanOrEqual;
use MakerMill\HydraType\Assertions\LessThan;
use MakerMill\HydraType\Assertions\LessThanOrEqual;
use MakerMill\HydraType\Assertions\MaxItems;
use MakerMill\HydraType\Assertions\MaxLength;
use MakerMill\HydraType\Assertions\MaxValue;
use MakerMill\HydraType\Assertions\MinItems;
use MakerMill\HydraType\Assertions\MinLength;
use MakerMill\HydraType\Assertions\MinValue;
use MakerMill\HydraType\Assertions\MatchesPattern;
use MakerMill\HydraType\Assertions\Negative;
use MakerMill\HydraType\Assertions\NonNegative;
use MakerMill\HydraType\Assertions\NonPositive;
use MakerMill\HydraType\Assertions\NotBlank;
use MakerMill\HydraType\Assertions\NotEqual;
use MakerMill\HydraType\Assertions\NotOneOf;
use MakerMill\HydraType\Assertions\OneOf;
use MakerMill\HydraType\Assertions\Positive;
use MakerMill\HydraType\Assertions\StartsWith;
use MakerMill\HydraType\HydrationException\AssertionException;
use MakerMill\HydraType\Interfaces\AssertionInterface;
use MakerMill\HydraType\Tests\Fixtures\BasicAssertionsRecord;

it('compiles each basic assertion into its expected condition and diagnostic', function (
    AssertionInterface $assertion,
    string $condition,
    string $message,
) {
    expect($assertion->compileCondition('$value'))->toBe($condition)
        ->and($assertion->message())->toBe($message);
})->with([
    'between' => [
        new Between(2.5, 4.5),
        '$value >= 2.5 && $value <= 4.5',
        'Value must be between 2.5 and 4.5',
    ],
    'equal' => [new Equal(2.5), '$value === 2.5', 'Value must be equal to 2.5'],
    'not equal' => [new NotEqual(2.5), '$value !== 2.5', 'Value must not be equal to 2.5'],
    'greater than' => [new GreaterThan(2.5), '$value > 2.5', 'Value must be greater than 2.5'],
    'greater than or equal' => [
        new GreaterThanOrEqual(2.5),
        '$value >= 2.5',
        'Value must be greater than or equal to 2.5',
    ],
    'less than' => [new LessThan(2.5), '$value < 2.5', 'Value must be less than 2.5'],
    'less than or equal' => [
        new LessThanOrEqual(2.5),
        '$value <= 2.5',
        'Value must be less than or equal to 2.5',
    ],
    'minimum value' => [new MinValue(2.5), '$value >= 2.5', 'Value must be greater than or equal to 2.5'],
    'maximum value' => [new MaxValue(2.5), '$value <= 2.5', 'Value must be less than or equal to 2.5'],
    'minimum length' => [
        new MinLength(2),
        'strlen($value) >= 2',
        'Length must be greater than or equal to 2',
    ],
    'maximum length' => [
        new MaxLength(2),
        'strlen($value) <= 2',
        'Length must be less than or equal to 2',
    ],
    'minimum items' => [
        new MinItems(2),
        'count($value) >= 2',
        'Number of items must be greater than or equal to 2',
    ],
    'maximum items' => [
        new MaxItems(2),
        'count($value) <= 2',
        'Number of items must be less than or equal to 2',
    ],
    'positive' => [new Positive(), '$value > 0', 'Value must be positive'],
    'negative' => [new Negative(), '$value < 0', 'Value must be negative'],
    'non-negative' => [new NonNegative(), '$value >= 0', 'Value must be non-negative'],
    'non-positive' => [new NonPositive(), '$value <= 0', 'Value must be non-positive'],
    'one of' => [
        new OneOf(['draft', 'published', 'draft']),
        '$value === "draft" || $value === "published"',
        'Value must be one of the configured values',
    ],
    'not one of' => [
        new NotOneOf(['deleted', 'blocked', 'deleted']),
        '$value !== "deleted" && $value !== "blocked"',
        'Value must not be one of the configured values',
    ],
    'starts with' => [
        new StartsWith('user_'),
        'str_starts_with($value, "user_")',
        'Value must start with the configured prefix',
    ],
    'ends with' => [
        new EndsWith('_id'),
        'str_ends_with($value, "_id")',
        'Value must end with the configured suffix',
    ],
    'contains' => [
        new Contains('account'),
        'str_contains($value, "account")',
        'Value must contain the configured fragment',
    ],
    'not blank' => [new NotBlank(), 'trim($value) !== \'\'', 'Value must not be blank'],
    'matches pattern' => [
        new MatchesPattern('/^[A-Z]{2}-\d{3}$/D'),
        'preg_match("/^[A-Z]{2}-\\\\d{3}\\$/D", $value) === 1',
        'Value must match the configured pattern',
    ],
]);

it('composes basic assertions around converted property values', function () {
    $record = hydrateObject(testHydraType(), BasicAssertionsRecord::class, [
        'age' => '18',
        'name' => 12,
        'tags' => ['fast'],
        'balance' => '-1',
        'code' => '7',
        'score' => '2.5',
        'status' => 'active',
        'identifier' => 'user_account_id',
        'label' => '  visible  ',
        'reference' => 'SE-123',
    ]);

    expect($record->values())->toBe([
        'age' => 18,
        'name' => '12',
        'tags' => ['fast'],
        'balance' => -1,
        'code' => 7,
        'score' => 2.5,
        'status' => 'active',
        'identifier' => 'user_account_id',
        'label' => '  visible  ',
        'reference' => 'SE-123',
    ]);
});

it('fails at the violated basic assertion boundary', function (
    string $property,
    mixed $value,
    string $assertionClass,
) {
    $data = [
        'age' => 18,
        'name' => 'good',
        'tags' => ['fast'],
        'balance' => -1,
        'code' => 7,
        'score' => 2.5,
        'status' => 'active',
        'identifier' => 'user_account_id',
        'label' => 'visible',
        'reference' => 'SE-123',
    ];
    $data[$property] = $value;

    try {
        testHydraType()->hydrate(BasicAssertionsRecord::class, $data);
    } catch (AssertionException $exception) {
        expect($exception->assertionClass())->toBe($assertionClass);
        return;
    }

    throw new RuntimeException('Expected hydration to fail an assertion.');
})->with([
    'below numeric range' => ['age', 17, Between::class],
    'above maximum length' => ['name', '12345', MaxLength::class],
    'below minimum items' => ['tags', [], MinItems::class],
    'not negative' => ['balance', 0, Negative::class],
    'not strictly equal' => ['code', 8, Equal::class],
    'not strictly equal after float conversion' => ['score', '2.6', Equal::class],
    'not in allowed values' => ['status', 'unknown', OneOf::class],
    'in denied values' => ['status', 'deleted', NotOneOf::class],
    'missing prefix' => ['identifier', 'account_id', StartsWith::class],
    'missing fragment' => ['identifier', 'user_profile_id', Contains::class],
    'missing suffix' => ['identifier', 'user_account', EndsWith::class],
    'blank string' => ['label', " \t\n", NotBlank::class],
    'pattern mismatch' => ['reference', 'se-123', MatchesPattern::class],
]);

it('rejects impossible assertion configuration', function (Closure $factory, string $message) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'reversed range' => [fn () => new Between(2.5, 1.5), 'lower bound'],
    'negative minimum length' => [fn () => new MinLength(-1), 'Minimum length'],
    'negative maximum length' => [fn () => new MaxLength(-1), 'Maximum length'],
    'negative minimum items' => [fn () => new MinItems(-1), 'Minimum item count'],
    'negative maximum items' => [fn () => new MaxItems(-1), 'Maximum item count'],
    'empty allowed values' => [fn () => new OneOf([]), 'OneOf values'],
    'empty denied values' => [fn () => new NotOneOf([]), 'NotOneOf values'],
    'empty prefix' => [fn () => new StartsWith(''), 'prefix'],
    'empty suffix' => [fn () => new EndsWith(''), 'suffix'],
    'empty fragment' => [fn () => new Contains(''), 'fragment'],
    'invalid pattern' => [fn () => new MatchesPattern('/[/'), 'valid PCRE pattern'],
]);

it('rejects non-scalar configured membership values', function () {
    $oneOf = new ReflectionClass(OneOf::class);
    $notOneOf = new ReflectionClass(NotOneOf::class);

    expect(fn () => $oneOf->newInstance([['nested']]))
        ->toThrow(InvalidArgumentException::class, 'strings, integers, floats, or booleans')
        ->and(fn () => $notOneOf->newInstance([['nested']]))
        ->toThrow(InvalidArgumentException::class, 'strings, integers, floats, or booleans');
});
