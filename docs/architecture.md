# Architecture

HydraType performs reflection, attribute inspection, converter selection, and
source generation when a hydrator is compiled. The cached hydrator then performs
direct operations specialized for its target class.

## Optional feature rule

An opt-in feature must not change the generated runtime path for a class that
does not opt into it. Additional compilation work is acceptable; additional
hydration or extraction work is not.

For an unselected feature, a generated hydrator must not gain:

- runtime registry or configuration lookups;
- per-object or per-property feature checks;
- unused state or constructor initialization;
- indirect calls through compiler abstractions; or
- allocations unrelated to the ordinary property operation.

Compiler abstractions should resolve to specialized PHP expressions. A plain
property should therefore remain equivalent to a direct assignment:

```php
$object->id = (int) $data['id'];
```

When a property selects a feature, only that property's generated expression
should contain its runtime cost.

Assertions follow the same boundary. An unasserted property remains one direct
assignment. An asserted property evaluates its complete converted value once,
checks each compiled condition, and assigns only after every condition succeeds:

```php
$hydraAssertionValue = (int) $data['age'];
if (!($hydraAssertionValue >= 18)) {
    throw AssertionException::forProperty(...);
}
$object->age = $hydraAssertionValue;
```

No collector, registry, or assertion state is passed through generated writers.
Failures allocate an exception; successful hydration pays only for explicitly
selected inline conditions.

## Verification

`GeneratedHydratorContractTest` is the structural guard for the unselected path.
It parses the generated camel-case and snake-case writer and reader closures for
a plain fixture and compares their bodies with the intended direct operations.
This avoids coupling the test to cache-derived class names or formatting outside
the hot property path.

Functional tests verify selected behavior. Benchmarks are required when a change
alters shared generated infrastructure or another operation executed for every
hydrated or extracted object. Benchmark results should be treated as measurements
with natural variance, not as a substitute for inspecting generated code.

The relevant cost boundaries are:

1. cold compilation, which happens when the generated cache is missing or stale;
2. one-time hydrator initialization and lazy reader or writer creation; and
3. repeated hydration and extraction, which is the primary optimization target.

## Object creation

The compiler selects object creation from target-class metadata. A class with no
constructor is created directly in generated code:

```php
$object = new Target();
```

If any constructor exists, including a private or protected constructor, the
generated hydrator retains one cached `ReflectionClass` and bypasses the
constructor with `newInstanceWithoutConstructor()`. Constructor visibility is not
a reason to reject a target because hydration intentionally bypasses it.

Abstract classes, interfaces, traits, and enums cannot be created by either path
and are rejected during compilation. Property count does not influence the
strategy: cross-version benchmarks found direct construction fastest at every
tested size when no constructor exists, while cached reflection remains the safe
general fallback.
