# HydraType

HydraType is a high-performance, compiled object hydrator and extractor for PHP.
It turns arrays from databases, APIs, and queues into typed objects and those
objects back into arrays, using generated PHP specialized for each class.

No schema, adapter, base class, interface, or setters are required.

## High-throughput hydration without mapping boilerplate

The PHP class is the mapping definition. HydraType reads its property types and
attributes, then compiles the work into direct object creation, conversion, and
property access:

- hydrate public, protected, and private properties directly;
- convert scalar values and backed enums automatically;
- handle camel-case and snake-case data without adapters;
- hydrate and extract nested object graphs;
- compile selected transformations and assertions into individual properties;
- process batches through specialized hot paths; and
- pre-generate the complete cache for minimal production startup cost.

Use HydraType as a simple hydrator, or add behavior property by property.
Features that a class does not select generate no work on its hydration path.

## Install

```shell
composer require makermill/hydratype:^1.0@beta
```

The beta stability flag is required until HydraType has a stable release.
HydraType requires PHP 8.2 or newer.

## Quick start

Given an ordinary class and a backed enum:

```php
enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

final class User
{
    public function __construct(
        private int $id,
        private string $displayName,
        private bool $enabled,
        private UserStatus $status,
    ) {
    }
}
```

Hydrate snake-case or camel-case data without configuring an adapter:

```php
use MakerMill\HydraType\HydraType;

$hydra = new HydraType();

$user = $hydra->hydrate(User::class, [
    'id' => '42',
    'display_name' => 'Ada Lovelace',
    'enabled' => 'yes',
    'status' => 'active',
]);
```

The return type is inferred as `User`. HydraType converts values to declared
property types and writes private and protected properties directly.

Extract the object to an array:

```php
use MakerMill\HydraType\NamingConvention;

$camelData = $hydra->extract($user);
$snakeData = $hydra->extract($user, NamingConvention::SnakeCase);
```

Use the optimized batch paths when working with several objects:

```php
$users = $hydra->hydrateMany(User::class, $inputRows);
$outputRows = $hydra->extractMany($users, NamingConvention::SnakeCase);
```

For an especially focused hot path, `$hydra->hydrator(User::class)` returns a
reusable class-specific hydrator. See
[hydration and extraction](docs/hydration.md) for naming, type conversion,
batch behavior, nullability, and constructor semantics.

## Performance is the feature

HydraType reflects each class once and generates executable PHP specialized for
that exact object shape. Repeated hydration is reduced to object creation,
inline conversion, and straight-line property assignment. Private properties
are accessed through reusable class-scoped closures, and batch operations select
a writer once for the complete batch.

Optional features follow a strict rule: a class that does not select a feature
does not pay for it during hydration or extraction. There are no runtime
registries, mapping walks, or mutator dispatch calls in the ordinary property
path.

This is not a generic runtime mapping pipeline with a cache placed in front of
it. The cache contains the optimized implementation for the target class. The
generated-code choices are backed by cross-version microbenchmarks and direct
competitor benchmarks. See the [architecture](docs/architecture.md) for the
rules that protect the fast path.

## Performance comparison

In the maintained benchmarks, HydraType was fastest for private-property
hydration on PHP 8.2 and PHP 8.5. Its public-property path was effectively tied
for fastest on PHP 8.2 and fastest on PHP 8.5. The optimized batch path came
within 16–23% of equivalent handwritten PHP.

The maintained competitor benchmark measures warmed hydration of correctly
typed five-property arrays into new objects with private promoted properties or
ordinary public typed properties. These are focused hot-path comparisons rather
than comparisons of every feature offered by each library. Relative values use
HydraType as the 1.00x baseline.

### Private properties

| Hydrator                   |     PHP 8.2 | Relative |     PHP 8.5 | Relative |
|----------------------------|------------:|---------:|------------:|---------:|
| HydraType                  |    280.0 ns |    1.00x |    296.7 ns |    1.00x |
| Ocramius GeneratedHydrator |    309.6 ns |    1.11x |    353.0 ns |    1.19x |
| EventSauce generated       |    339.8 ns |    1.21x |    369.2 ns |    1.24x |
| JoliCode AutoMapper 10     |           — |        — |    485.2 ns |    1.64x |
| Patchlevel Hydrator        |    789.7 ns |    2.82x |    709.4 ns |    2.39x |
| Laminas ReflectionHydrator |  1,225.9 ns |    4.38x |  1,069.9 ns |    3.61x |
| Crell Serde (array)        |  7,955.1 ns |   28.41x |  7,923.5 ns |   26.71x |
| Symfony PropertyNormalizer |  8,473.8 ns |   30.26x |  8,413.1 ns |   28.36x |
| Sunrise Hydrator           |  9,650.7 ns |   34.47x |  9,436.6 ns |   31.81x |
| Valinor                    | 10,808.6 ns |   38.60x | 11,339.5 ns |   38.22x |

### Public properties

| Hydrator                         |     PHP 8.2 | Relative |     PHP 8.5 | Relative |
|----------------------------------|------------:|---------:|------------:|---------:|
| HydraType                        |    193.0 ns |    1.00x |    194.2 ns |    1.00x |
| Ocramius GeneratedHydrator       |    190.7 ns |    0.99x |    209.6 ns |    1.08x |
| EventSauce generated             |           — |        — |           — |        — |
| JoliCode AutoMapper 10           |           — |        — |  1,207.2 ns |    6.22x |
| Patchlevel Hydrator              |    808.5 ns |    4.19x |    718.7 ns |    3.70x |
| Laminas ObjectPropertyHydrator   |    958.0 ns |    4.96x |    851.8 ns |    4.39x |
| Symfony PropertyNormalizer       |  8,040.0 ns |   41.66x |  7,624.8 ns |   39.26x |
| Crell Serde (array)              |  8,088.4 ns |   41.91x |  7,968.8 ns |   41.03x |
| Sunrise Hydrator                 |  9,132.6 ns |   47.32x |  8,832.3 ns |   45.48x |
| Valinor                          | 10,367.2 ns |   53.72x | 10,734.7 ns |   55.28x |

HydraType was fastest for private properties in both environments and for
public properties on PHP 8.5. The PHP 8.2 public paths were effectively tied:
HydraType and Ocramius were separated by about 1% and exchanged order between
individual rounds. Ocramius directly assigns keys that are present and skips
missing keys. HydraType additionally checks required keys and coerces values to
the declared property types.

> **Faster still in batches**
>
> In the maintained batch benchmark, `hydrateMany()` is **37% faster** per
> object than repeated `hydrate()` calls on PHP 8.2 and **29% faster** on PHP
> 8.5. In a like-for-like comparison with handwritten PHP, it comes within
> about 23% and 16% respectively.

See the [benchmark methodology and complete conclusions](benchmarks/README.md).

## Opt-in behavior

Attributes add behavior only to the properties that select it:

```php
use MakerMill\HydraType\Assertions\NotEmpty;
use MakerMill\HydraType\Mutators\DateTimeFormat;
use MakerMill\HydraType\Mutators\JsonValue;
use MakerMill\HydraType\Mutators\Trim;
use MakerMill\HydraType\Rules\Optional;

final class Event
{
    #[Optional]
    private ?string $note = null;

    public function __construct(
        #[Trim]
        #[NotEmpty]
        private string $name,
        #[DateTimeFormat('Y-m-d H:i:s')]
        private DateTimeImmutable $startsAt,
        #[JsonValue]
        private array $metadata,
    ) {
    }
}
```

- Mutators transform incoming values and may also transform extracted values.
- Assertions are compiled fail-fast guards, not a general validation system.
- `Optional` preserves a declared property or promoted parameter default when the input key is absent.
- Backed enums and concrete nested objects work without attributes.

See [mutators](docs/mutators.md), [assertions](docs/assertions.md), and
[nested hydration](docs/nested-hydration.md) for their complete behavior and
extension contracts.

## Production fast path

The default `CacheMode::Auto` is convenient during development: it generates a
missing hydrator and refreshes one whose source dependencies changed.

For production, warm all application entry points during build or deployment:

```php
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydratorCache;

$configuration = new Configuration(
    hydratorDirectory: __DIR__ . '/../var/cache/hydratype',
);

(new HydratorCache($configuration))->warm(
    User::class,
    Order::class,
    Invoice::class,
);
```

### Keep the warm-up list in sync

As an application grows, manually maintaining every class passed to `warm()` is
easy to forget. [HydraType Tools](https://github.com/makermill/hydratype-tools)
generates this class list from the project source. See its documentation for
current installation and usage.

Whether the class list is maintained manually or generated, use the warmed
cache in read-only mode at runtime:

```php
use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydraType;

$hydra = new HydraType(new Configuration(
    hydratorDirectory: __DIR__ . '/../var/cache/hydratype',
    cacheMode: CacheMode::ReadOnly,
));
```

Read-only mode skips source reflection, dependency fingerprinting, locks, and
all cache writes. It fails immediately when an expected artifact is missing.
The generated hydration path is identical in automatic and read-only modes.

See [cache and production deployment](docs/cache.md) for invalidation,
concurrency, nested warm-up, custom directories and namespaces, and selective
cache clearing.

## Supported behavior at a glance

| Capability      | Behavior                                                               |
|-----------------|------------------------------------------------------------------------|
| Input names     | Automatic camel case or snake case                                     |
| Output names    | Camel case by default; snake case on request                           |
| Type conversion | Scalars, booleans, arrays, objects, and backed enums                   |
| Property access | Public, protected, and private instance properties                     |
| Object creation | Direct `new` without a constructor; constructor bypass otherwise       |
| Nested objects  | Concrete classes automatically; abstractions through `HydrateAs`       |
| Transformations | Compiled, composable property mutators                                 |
| Assertions      | Compiled, opt-in, fail-fast property guards                            |
| Batches         | Specialized `hydrateMany()` and `extractMany()` paths                  |
| Cache           | Automatic development mode and pre-generated read-only production mode |

Unbacked enums, union types, intersection types, inferred collections of nested
objects, and runtime object-graph cycles are not supported.

## Documentation

- [Hydration and extraction](docs/hydration.md) — naming, conversions, batches,
  nullability, construction, and errors.
- [Nested hydration](docs/nested-hydration.md) — recursive objects,
  `HydrateAs`, cache behavior, and boundaries.
- [Mutators](docs/mutators.md) — every built-in transformation and the custom
  extension contract.
- [Assertions](docs/assertions.md) — built-in and custom fail-fast guards.
- [Cache and production deployment](docs/cache.md) — modes, warm-up,
  invalidation, concurrency, and clearing.
- [Public API boundary](docs/public-api.md) — supported consumer and extension
  types.
- [Architecture](docs/architecture.md) — generated-code rules and structural
  verification.
- [Benchmark conclusions](benchmarks/README.md) — measurements behind the
  implementation choices.

## Development

Install dependencies and run the complete checks:

```shell
composer install
composer check
```

The repository includes matched PHP 8.2 and PHP 8.5 Docker environments:

```shell
composer docker:build
composer check:docker
```

The competitor benchmark can be run against either image:

```shell
composer benchmark:competitors:docker:php82 -- 20000 9
composer benchmark:competitors:docker:php85 -- 20000 9
```

Individual test, static-analysis, coverage, and benchmark commands are defined
in `composer.json`.
