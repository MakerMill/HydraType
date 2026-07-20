# Hydration and extraction

HydraType compiles property access, naming, and type conversion for a target
class. It assigns property state directly rather than calling constructors or
setters.

## Batch operations

Use `hydrateMany()` to select and reuse one generated writer for a batch:

```php
$users = $hydra->hydrateMany(User::class, [
    ['id' => 1, 'display_name' => 'Ada Lovelace'],
    ['id' => 2, 'display_name' => 'Grace Hopper'],
]);
```

A batch must use one naming convention throughout. The writer is selected from
the first row and reused for every remaining row. An empty hydration batch is
rejected.

Extraction uses camel case by default. Select snake case explicitly when
needed:

```php
use MakerMill\HydraType\NamingConvention;

$data = $hydra->extract($user);
$snakeData = $hydra->extract($user, NamingConvention::SnakeCase);
$rows = $hydra->extractMany($users, NamingConvention::SnakeCase);
```

`extractMany()` expects objects of the same concrete class. An empty object list
returns an empty array.

For a focused hot path, resolve the class-specific hydrator once:

```php
$userHydrator = $hydra->hydrator(User::class);

$user = $userHydrator->hydrate($data);
$users = $userHydrator->hydrateMany($rows);
$data = $userHydrator->extract($user);
```

## Naming conventions

Hydration automatically recognizes camel-case and snake-case keys. Each input
object must consistently use one convention.

Property names are treated as camel case or snake case when generated keys are
calculated. Unusual capitalization and consecutive underscores are not
special-cased. Classes whose properties collapse to the same generated key are
rejected during compilation.

## Type conversion

Basic conversion is compiled directly into each property assignment:

| Property type | Input conversion |
|---|---|
| `int` | PHP integer cast |
| `float` | PHP float cast |
| `string` | PHP string cast |
| `array` | PHP array cast |
| `object` | PHP object cast |
| `bool` | Known true representations map to `true`; everything else maps to `false` |
| Backed enum | The backing type is cast and passed to `tryFrom()` |
| `mixed` or untyped | The value is assigned unchanged |

The recognized true values are `true`, `1`, `'1'`, `'t'`, `'Y'`, `'true'`,
`'yes'`, and `'on'`.

Backed enums are extracted to their backing values. Unbacked enums, union types,
and intersection types are not supported. Internal PHP classes such as
`DateTimeImmutable` require an explicit mutator that produces the declared
type.

## Nullable and optional properties

A nullable property accepts an explicit `null`. A missing nullable key also
becomes `null`.

Use `Optional` when a missing key should preserve an existing property default:

```php
use MakerMill\HydraType\Rules\Optional;

final class Preferences
{
    #[Optional]
    private string $theme = 'system';
}
```

The default must be declared on the property itself. A default on a promoted
constructor parameter is only applied when that constructor runs, and HydraType
does not call target constructors.

## Object construction

- A class without a constructor is created directly with `new`.
- A class with any constructor is created without invoking that constructor.
- Constructor validation, invariants, dependency setup, and side effects are
  therefore not executed.
- Abstract classes, interfaces, traits, and enums cannot be hydration targets.
- Static properties are ignored.

Use HydraType for objects whose property state can safely be established this
way.

## Errors and validation boundary

HydraType converts and transforms values and can enforce selected local
assertions. It is not a general validation framework. Use a dedicated validator
when business-rule graphs, multiple collected failures, localization, or rich
input reports matter.

Invalid target definitions, cache failures, and assignment failures are
reported through `HydrationException`. Assertion failures use its
`AssertionException` subclass. Exceptions produced by explicit mutators, such
as `JsonException`, can propagate directly.
