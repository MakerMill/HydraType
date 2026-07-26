# HydraType

HydraType is a compiled object hydrator and extractor built to be one of the
fastest general-purpose hydrators available for PHP. It combines generated-code
performance with automatic type conversion, nested objects, composable
mutators, assertions, and extraction in both camel case and snake case.

No schema, adapter, base class, interface, or setters are required.

## High-throughput hydration without mapping boilerplate

HydraType turns arrays from databases, APIs, and queues into typed objects—and
extracts those objects back to arrays—with an intentionally small API:

- hydrate public, protected, and private properties directly;
- convert scalar values and backed enums automatically;
- handle camel-case and snake-case data without adapters;
- hydrate and extract nested object graphs;
- compile selected transformations and assertions into individual properties;
- process batches through specialized hot paths; and
- pre-generate the complete cache for minimal production startup cost.

The basic path stays basic. Advanced behavior is opt-in and is compiled only
into the properties that use it, so a feature does not slow down classes that
do not select it.

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
competitor benchmarks. See the [benchmark conclusions](benchmarks/README.md) for
the measurements and [architecture](docs/architecture.md) for the rules that
protect the fast path.

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

Use `hydrateMany()` and `extractMany()` for batches. For an especially focused
hot path, `$hydra->hydrator(User::class)` returns a reusable class-specific
hydrator. See [hydration and extraction](docs/hydration.md) for naming, type
conversion, batch behavior, nullability, and constructor semantics.

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

Applications do not have to maintain that root-class list by hand.
[HydraType Tools](https://github.com/makermill/hydratype-tools) scans statically
visible `hydrate()`, `hydrateMany()`, and `hydrator()` calls and generates a
deterministic warm-up manifest:

```shell
composer require --dev makermill/hydratype-tools:^1.0@beta
vendor/bin/hydratype discover src --output=var/cache/hydratype-classes.php
```

Run discovery during the build stage, before development dependencies are
removed with `composer install --no-dev`.

Use the generated roots with the same warm-up API:

```php
$rootClasses = require __DIR__ . '/../var/cache/hydratype-classes.php';

(new HydratorCache($configuration))->warm(...$rootClasses);
```

The tool reports dynamic targets it cannot prove statically, while HydraType's
warm-up follows each discovered root through its complete nested hydration
graph. See the [HydraType Tools documentation](https://github.com/makermill/hydratype-tools)
for strict discovery and deployment workflows.

Then use the same cache in read-only mode at runtime:

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

| Capability | Behavior |
|---|---|
| Input names | Automatic camel case or snake case |
| Output names | Camel case by default; snake case on request |
| Type conversion | Scalars, booleans, arrays, objects, and backed enums |
| Property access | Public, protected, and private instance properties |
| Object creation | Direct `new` without a constructor; constructor bypass otherwise |
| Nested objects | Concrete classes automatically; abstractions through `HydrateAs` |
| Transformations | Compiled, composable property mutators |
| Assertions | Compiled, opt-in, fail-fast property guards |
| Batches | Specialized `hydrateMany()` and `extractMany()` paths |
| Cache | Automatic development mode and pre-generated read-only production mode |

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
