# Generated cache and production deployment

HydraType generates executable PHP tailored to each target class. The cache is
therefore part of both correctness and performance, not merely an optional
optimization.

## Cache modes

`CacheMode::Auto` is the default. It creates the cache directory when needed,
checks a cached hydrator when first resolved, and recompiles a missing entry,
malformed header, or changed dependency fingerprint.

`CacheMode::ReadOnly` trusts a pre-generated cache. It does not inspect target
source files, create directories or lock files, or compile generated code. A
missing entry fails immediately and reports the expected path.

Both modes use the same generated identity, so a cache produced in automatic
mode can be deployed and loaded in read-only mode.

## Recommended production workflow

Warm every application entry point during the build or deployment:

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

`warm()` follows reachable nested hydrators, regenerates every discovered
artifact under its normal cache lock, and verifies the completed graph against
current dependency fingerprints. Repeated classes, shared descendants, and
cyclic class definitions are handled once.

Use the same directory and namespace in read-only mode at runtime:

```php
use MakerMill\HydraType\CacheMode;
use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydraType;

$hydra = new HydraType(new Configuration(
    hydratorDirectory: __DIR__ . '/../var/cache/hydratype',
    cacheMode: CacheMode::ReadOnly,
));
```

Read-only mode skips dependency fingerprinting, source reflection, locks, and
all writes. The generated hydration and extraction paths are identical in both
modes.

Warm-up requires an automatic configuration. Its returned array maps every
discovered target class to its generated file for build tooling that needs to
inspect or package the artifacts.

## Configuration and invalidation

By default, HydraType uses a project- and operating-system-user-specific
directory under the system temporary directory and the generated namespace
`MakerMill\HydraType\Generated`. Because generated hydrators are executable PHP,
HydraType rejects a default directory owned by another user, accessible by its
group or other users, or reached through a symbolic link. The default directory
is created with owner-only permissions.

Configure the directory explicitly for deployed caches. An explicitly
configured directory is part of the application's trusted deployment state and
must not be writable by untrusted users:

```php
$configuration = new Configuration(
    hydratorNamespace: 'App\\Generated\\Hydrators',
    hydratorDirectory: __DIR__ . '/../var/cache/hydratype',
);
```

Each generated PHP file contains a compact fingerprint header covering the
target class, its parents and traits, relevant property classes, and selected
hydration or extraction attribute classes. Source contents rather than file
timestamps determine freshness.

Code called transitively by a custom mutator is not followed. Force regeneration
if such a helper changes the expression emitted by a mutator without changing
the mutator class itself.

## Concurrency

Concurrent compilation is serialized per hydrator. Generated source is written
and flushed to a temporary file in the cache directory, then atomically moved
into place. Another process cannot load a partially written hydrator.

## Clearing selected entries

`HydratorCache::clear()` removes generated files for explicitly supplied
classes while holding each hydrator's normal cache lock:

```php
(new HydratorCache($configuration))->clear(
    User::class,
    Order::class,
);
```

Missing entries are ignored, unrelated files are untouched, and persistent lock
files remain. Clearing requires automatic mode and affects only disk. PHP cannot
unload an already loaded generated class, so clear caches from build,
deployment, or development tooling rather than as hot reload inside a running
application.
