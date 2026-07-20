# Public API boundary

HydraType exposes a small consumer API and a compile-time extension API. A public
PHP namespace alone does not make a type a supported compatibility contract.

## Consumer API

Applications may depend on:

- `HydraType`, `Configuration`, `CacheMode`, and `NamingConvention` for normal
  hydration and extraction;
- `HydratorCache` for explicit deployment warm-up and cache clearing;
- `HydratorInterface` when retaining or passing a class-specific hydrator;
- `HydratorFactory::create()` for advanced direct hydrator resolution; and
- `HydrationException` as the library failure base class and its specialized
  `AssertionException` subclass.

The public attributes in `Mutators`, `Assertions`, and `Rules` are supported
configuration API.

## Extension API

Custom compiled behavior may implement `MutatorInterface`,
`ExtractionMutatorInterface`, or `AssertionInterface`. `Type` and `PhpLiteral`
are supported helpers for describing output and embedding configured scalar
values in generated PHP.

These extensions produce PHP expressions during compilation. They are not
runtime callbacks, and their generated expressions must be valid for every
value they receive.

## Implementation details

Types marked `@internal` belong to reflection analysis, validation, source
generation, generated-cache storage, or converter selection. They may change
without compatibility guarantees. Public concrete classes are final unless
inheritance is part of their stated role; extension is provided through the
interfaces listed above.

Generated hydrator class names, source code, cache file names, metadata, and
namespace contents are artifacts rather than public API. Applications may
inspect them while developing, but must not instantiate them directly or depend
on their shape.
