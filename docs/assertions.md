# Assertions

Assertions are opt-in, fail-fast property guards. They run after mutators and
inferred type conversion, so they inspect the value that would actually be
assigned.

Assertions are compiled only for properties that select them. An asserted
property pays for one temporary value and one inline branch per assertion;
properties without assertions retain their direct generated assignment.

For example, this accepts only a short, non-blank identifier with the expected
prefix:

```php
use MakerMill\HydraType\Assertions\MaxLength;
use MakerMill\HydraType\Assertions\NotBlank;
use MakerMill\HydraType\Assertions\StartsWith;

final class Contact
{
    public function __construct(
        #[StartsWith('user_')]
        #[MaxLength(64)]
        #[NotBlank]
        private string $identifier,
    ) {
    }
}
```

The first failure throws `AssertionException`. It identifies the target class,
property, assertion class, and reason without copying the rejected value into
the exception. HydraType stops immediately; it does not collect failures or
return partially hydrated objects.

## Built-in assertions

Numeric comparison arguments accept integers and floats. All equality and
membership checks are strict and run after HydraType has converted the input to
the declared property type.

| Assertion | Accepted value |
| --- | --- |
| `Between($from, $to)` | Between the inclusive numeric bounds |
| `Equal($value)` | Strictly equal to the configured number |
| `NotEqual($value)` | Not strictly equal to the configured number |
| `GreaterThan($value)` | Greater than the configured number |
| `GreaterThanOrEqual($value)` | Greater than or equal to the configured number |
| `LessThan($value)` | Less than the configured number |
| `LessThanOrEqual($value)` | Less than or equal to the configured number |
| `MinValue($value)` | Greater than or equal to the configured number |
| `MaxValue($value)` | Less than or equal to the configured number |
| `Positive` | Greater than zero |
| `Negative` | Less than zero |
| `NonNegative` | Greater than or equal to zero |
| `NonPositive` | Less than or equal to zero |

Length uses PHP's `strlen()`, so it measures bytes rather than Unicode
characters. Item assertions use `count()`.

| Assertion | Accepted value |
| --- | --- |
| `MinLength($length)` | String with at least the configured byte length |
| `MaxLength($length)` | String with at most the configured byte length |
| `MinItems($count)` | Array with at least the configured number of items |
| `MaxItems($count)` | Array with at most the configured number of items |
| `NotEmpty` | String other than `''`, or array other than `[]` |
| `NotBlank` | String containing something besides PHP trim whitespace |

String content checks are case-sensitive. `StartsWith`, `EndsWith`, `Contains`,
and `MatchesPattern` are repeatable when a property needs several independent
conditions.

| Assertion | Accepted value |
| --- | --- |
| `StartsWith($prefix)` | String beginning with the non-empty prefix |
| `EndsWith($suffix)` | String ending with the non-empty suffix |
| `Contains($fragment)` | String containing the non-empty fragment |
| `MatchesPattern($pattern)` | String matched by the configured PCRE pattern |
| `OneOf($values)` | Strictly equal to one configured scalar value |
| `NotOneOf($values)` | Strictly unequal to every configured scalar value |

Membership lists accept strings, integers, floats, and booleans. They must not
be empty; repeated values are compiled once. PCRE patterns are checked when the
assertion is instantiated during hydrator compilation, not for every hydrated
object.

```php
use MakerMill\HydraType\Assertions\MatchesPattern;
use MakerMill\HydraType\Assertions\NotOneOf;
use MakerMill\HydraType\Assertions\OneOf;

final class Job
{
    public function __construct(
        #[OneOf(['queued', 'running', 'complete'])]
        #[NotOneOf(['complete'])]
        private string $status,
        #[MatchesPattern('/^[A-Z]{2}-\d{3}$/D')]
        private string $reference,
    ) {
    }
}
```

The overlapping membership assertions above deliberately demonstrate
composition: `complete` passes `OneOf` and is then rejected by `NotOneOf`.
Normally one membership assertion is sufficient.

Invalid configuration fails while the hydrator is compiled. This includes
reversed `Between` bounds, negative length or item limits, empty membership
lists, empty string-search arguments, and malformed PCRE patterns. These checks
do not run in the generated hydration path.

## Custom assertions

Assertions are property attributes that contribute an inline condition while a
hydrator is compiled. They are not resolved or dispatched during hydration. A
property without an assertion therefore retains its ordinary direct assignment.

An assertion implements `AssertionInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Hydration;

use Attribute;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MinimumLength implements AssertionInterface
{
    public function __construct(private int $length)
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Length must not be negative.');
        }
    }

    public function compileCondition(string $valueExpression): string
    {
        return "strlen({$valueExpression}) >= {$this->length}";
    }

    public function message(): string
    {
        return "Value must contain at least {$this->length} bytes.";
    }
}
```

`compileCondition()` receives the generated temporary containing the complete
mutated and converted property value. It must return a PHP expression that is
`true` when that value is accepted. `message()` is evaluated at compile time and
embedded as a PHP literal in the failure path.

Assertion implementations are trusted compiler extensions because their
conditions become executable generated PHP. Their source files participate in
cache fingerprinting.

## Runtime behavior

All mutators and inferred type conversion run before assertions. Multiple
assertions inspect the same temporary value in attribute declaration order. The
property is assigned only after every condition succeeds.

Nullable input bypasses assertions when its final value is `null`. A missing
`Optional` value preserves its property or promoted parameter default without evaluating its
assertions. Assertions apply during hydration only; extraction returns existing
object state without rechecking it.

The first failed assertion throws `AssertionException`. It identifies the target
class, property, assertion class, and configured reason, but deliberately omits
the rejected value because input may contain secrets. Batch and nested hydration
also fail immediately and propagate the original child failure. HydraType does
not collect failures or return partially hydrated objects.
