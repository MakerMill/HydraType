# Mutators

Mutators are opt-in property attributes. Their transformations are compiled
into the generated hydrator, so properties without a mutator do not pay for a
mutator lookup, registry, or callback.

## Built-in mutators

| Mutator | Hydration result | Extraction behavior |
| --- | --- | --- |
| `Trim`, `LeftTrim`, `RightTrim` | Removes configured edge characters | Unchanged |
| `Lowercase`, `Uppercase` | Changes byte-oriented string case | Unchanged |
| `StringReplace` | Replaces a literal string | Unchanged |
| `RegexReplace` | Applies a PCRE replacement | Unchanged |
| `Substring` | Selects part of a string | Unchanged |
| `EmptyStringToNull` | Converts exactly `''` to `null` | Unchanged |
| `DelimitedString` | Splits a string into an array | Joins the array into a string |
| `MapValue` | Translates configured scalar values | Unchanged |
| `Round` | Rounds a numeric value | Unchanged |
| `Clamp` | Restricts a numeric value to inclusive bounds | Unchanged |
| `Absolute` | Produces the absolute numeric value | Unchanged |
| `JsonDecode` | Decodes JSON | Unchanged |
| `JsonValue` | Decodes JSON | Encodes JSON |
| `DateTimeFormat` | Creates a `DateTimeImmutable` | Formats the date and time |
| `HydrateAs` | Selects a concrete nested hydration target | Uses nested extraction |

Every transformation is emitted only for properties carrying its attribute.
Configuration checks run while the hydrator is compiled, not while objects are
hydrated.

### String normalization

`LeftTrim`, `RightTrim`, and `Trim` remove characters from a string during
hydration. They use PHP's default whitespace list unless a non-empty custom
character list is supplied:

```php
use MakerMill\HydraType\Mutators\LeftTrim;
use MakerMill\HydraType\Mutators\RightTrim;
use MakerMill\HydraType\Mutators\Trim;

final class NormalizedInput
{
    public function __construct(
        #[Trim]
        private string $name,
        #[LeftTrim('/')]
        private string $path,
        #[RightTrim(' .')]
        private string $reference,
    ) {
    }
}
```

`Lowercase` and `Uppercase` compile to PHP's byte-oriented `strtolower()` and
`strtoupper()` functions. They do not require `mbstring`:

```php
use MakerMill\HydraType\Mutators\Lowercase;
use MakerMill\HydraType\Mutators\Trim;

final class Contact
{
    public function __construct(
        #[Trim]
        #[Lowercase]
        private string $email,
    ) {
    }
}
```

These mutators affect hydration only.

### Substring

`Substring` uses PHP's byte-oriented `substr()`. Its offset and optional length
support the same positive, zero, and negative values as PHP:

```php
use MakerMill\HydraType\Mutators\Substring;

final class FixedWidthRecord
{
    public function __construct(
        #[Substring(0, 8)]
        private string $identifier,
    ) {
    }
}
```

It affects hydration only.

### StringReplace

`StringReplace` compiles a literal replacement into hydration. The attribute is
repeatable, and replacements compose in declaration order:

```php
use MakerMill\HydraType\Mutators\StringReplace;
use MakerMill\HydraType\Mutators\Trim;

final class Article
{
    public function __construct(
        #[Trim]
        #[StringReplace(' ', '-')]
        #[StringReplace('--', '-')]
        private string $slug,
    ) {
    }
}
```

This converts `"  Hydra  Type  "` to `"Hydra-Type"`. Search strings must not
be empty. `StringReplace` affects hydration only.

`RegexReplace` is the PCRE counterpart. It is repeatable and composes in
declaration order just like `StringReplace`:

```php
use MakerMill\HydraType\Mutators\RegexReplace;

final class Slug
{
    public function __construct(
        #[RegexReplace('/\s+/', '-')]
        #[RegexReplace('/-+/', '-')]
        private string $value,
    ) {
    }
}
```

The pattern is checked during hydrator compilation. A replacement failure, for
example invalid UTF-8 supplied to a Unicode pattern, throws a
`RuntimeException`. `RegexReplace` affects hydration only.

### EmptyStringToNull

`EmptyStringToNull` converts exactly `''` to `null`. It is intended for nullable
string properties and can follow `Trim` to normalize whitespace-only input:

```php
use MakerMill\HydraType\Mutators\EmptyStringToNull;
use MakerMill\HydraType\Mutators\Trim;

final class Description
{
    public function __construct(
        #[Trim]
        #[EmptyStringToNull]
        private ?string $value,
    ) {
    }
}
```

It affects hydration only.

### DelimitedString

`DelimitedString` represents an array as a separated external string. It uses
`explode()` during hydration and `implode()` during extraction:

```php
use MakerMill\HydraType\Mutators\DelimitedString;

final class TaggedRecord
{
    /** @param list<string> $tags */
    public function __construct(
        #[DelimitedString('|')]
        private array $tags,
    ) {
    }
}
```

The separator must not be empty. An empty input string becomes `['']`, matching
PHP's normal `explode()` behavior.

### MapValue

`MapValue` translates configured scalar values before normal property type
conversion:

```php
use MakerMill\HydraType\Mutators\MapValue;

final class Account
{
    public function __construct(
        #[MapValue([
            'enabled' => true,
            'disabled' => false,
            1 => true,
            0 => false,
        ])]
        private bool $enabled,
    ) {
    }
}
```

Unmapped input passes through unchanged. Maps must be non-empty and replacement
values may be strings, integers, floats, or booleans. Integer keys follow
normal PHP array-key behavior, so a numeric string such as `'1'` matches the
integer key `1`. `MapValue` affects hydration only.

### Numeric transformations

`Round`, `Clamp`, and `Absolute` cast incoming values to floats before applying
their transformation. Normal inferred conversion still runs afterward when the
declared property type is `int`:

```php
use MakerMill\HydraType\Mutators\Absolute;
use MakerMill\HydraType\Mutators\Clamp;
use MakerMill\HydraType\Mutators\Round;

final class Measurement
{
    public function __construct(
        #[Round(2, PHP_ROUND_HALF_EVEN)]
        private float $amount,
        #[Clamp(0, 100)]
        private int $percentage,
        #[Absolute]
        private float $distance,
    ) {
    }
}
```

`Round` accepts PHP's four `PHP_ROUND_HALF_*` modes. `Clamp` uses inclusive
integer or float bounds and rejects a minimum greater than its maximum. These
mutators affect hydration only.

### JSON

`JsonDecode` decodes incoming JSON and leaves the stored array or object
unchanged during extraction:

```php
use MakerMill\HydraType\Mutators\JsonDecode;

final class Settings
{
    public function __construct(
        #[JsonDecode]
        private array $values,
        #[JsonDecode(associative: false, depth: 64)]
        private object $metadata,
    ) {
    }
}
```

`JsonValue` treats JSON as the external representation in both directions. It
decodes during hydration and encodes during extraction:

```php
use MakerMill\HydraType\Mutators\JsonValue;

final class ApiConfiguration
{
    public function __construct(
        #[JsonValue(encodeFlags: JSON_UNESCAPED_SLASHES)]
        private array $settings,
        #[JsonValue(associative: false)]
        private object $metadata,
        #[JsonValue]
        private ?array $optionalSettings,
    ) {
    }
}
```

Both attributes always enable `JSON_THROW_ON_ERROR`. `JsonValue` accepts a
shared depth plus separate `decodeFlags` and `encodeFlags`. Nullable values
remain `null` without calling either JSON function.

### DateTimeFormat

`DateTimeFormat` creates a `DateTimeImmutable` from a PHP-supported date/time
string and formats it during extraction:

```php
use DateTimeImmutable;
use MakerMill\HydraType\Mutators\DateTimeFormat;

final class Event
{
    public function __construct(
        #[DateTimeFormat('Y-m-d H:i:s')]
        private DateTimeImmutable $startsAt,
    ) {
    }
}
```

The format controls extraction. Hydration uses the normal
`DateTimeImmutable` string parser.

## Custom mutators

Mutators are property attributes that contribute PHP expressions while a
hydrator is compiled. They are not called through a registry during hydration or
extraction. A property without a mutator therefore pays no mutator-related
runtime cost.

A hydration mutator implements `MutatorInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Hydration;

use Attribute;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Base64Value implements MutatorInterface, ExtractionMutatorInterface
{
    public function compile(string $inputExpression): string
    {
        return 'base64_decode((string) (' . $inputExpression . '), true)';
    }

    public function outputType(): string
    {
        return Type::String->value;
    }

    public function compileExtraction(string $inputExpression): string
    {
        return 'base64_encode((string) (' . $inputExpression . '))';
    }
}
```

It can then be attached directly to a property:

```php
final class ApiToken
{
    public function __construct(
        #[Base64Value]
        private string $value,
    ) {
    }
}
```

`compile()` receives the current input expression and must return the expression
that produces the next value. `outputType()` describes that value. If it already
matches the property type, HydraType does not add an inferred type conversion.
Otherwise, the normal converter for the property type is applied afterward.

Implementing `ExtractionMutatorInterface` is optional. Its
`compileExtraction()` method converts the internal property expression to its
external representation.

## Composition order

Multiple mutator attributes compose in declaration order during hydration:

```php
#[Decode]
#[Transform]
private string $value;
```

This compiles as `Transform(Decode($input))`. Extraction unwinds bidirectional
mutators in reverse declaration order, producing `Encode(Untransform($value))`.
This makes a hydrate-and-extract round trip predictable.

Nullable properties bypass all hydration and extraction mutators when their
value is `null`. An `Optional` property applies its hydration mutators only when
the corresponding input key exists; otherwise, its current property default is
preserved.

## Contract boundaries

Mutators transform values; they are not a validation system. Use compiled
assertions for opt-in, fail-fast property guards. Runtime failures from a
mutator's compiled expression propagate to the caller.

Attribute instances are created during hydrator compilation. Constructor
arguments may configure generated code, but user-provided values must be emitted
as valid PHP literals. `PhpLiteral::string()` is available for string values.
Mutator implementations are trusted code because their returned expressions are
written into the generated hydrator and executed by PHP.
