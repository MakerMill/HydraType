# Nested hydration

Concrete, user-defined class properties are hydrated recursively without an
attribute:

```php
final class Address
{
    public function __construct(
        private string $streetName,
        private Country $country,
    ) {
    }
}

final class User
{
    public function __construct(private Address $address)
    {
    }
}

$user = $hydra->hydrate(User::class, [
    'address' => [
        'street_name' => 'Compiler Road',
        'country' => ['country_code' => 'SE'],
    ],
]);
```

Each level detects camel-case or snake-case input independently. If an input
value is already an instance of the target class, it is assigned without
rehydration. Extraction recursively returns arrays and propagates the selected
naming convention through the complete graph.

## Selecting a concrete class

An interface or abstract property does not identify a class to create. Select
one explicitly with `HydrateAs`:

```php
use MakerMill\HydraType\Mutators\HydrateAs;

final class Payment
{
    public function __construct(
        #[HydrateAs(CardPaymentMethod::class)]
        private PaymentMethod $method,
    ) {
    }
}
```

The selected class must be a concrete, user-defined class assignable to the
property type. `HydrateAs` composes in declaration order with mutators, so a
`JsonValue` may decode object-shaped JSON before nested hydration and encode the
extracted nested array again.

## Runtime and cache behavior

Nested child hydrators are resolved once when the generated reader or writer is
first created, then captured by the reused closure. Classes without nested
properties retain the same generated property path.

Nested classes and their relevant attributes participate in cache dependency
tracking. Cache warm-up follows the reachable graph once, including shared
descendants and cyclic class definitions.

## Boundaries

Collections of nested objects are not inferred from PHPDoc and require
application-level handling. Cyclic class definitions are safe to warm and
resolve, but extracting an object graph containing an actual runtime cycle is
not supported.
