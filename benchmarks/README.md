# Benchmark conclusions

These benchmarks were created to decide how a generated HydraType hydrator should
write values to private properties.

Except for the current competitor comparison described below, the recorded runs
used PHP 8.2.32 in the project Docker image and PHP 8.5.8 on the host. CLI JIT
was inactive and Xdebug was loaded with its modes disabled.

Unless a section says otherwise, reported values are medians. They are useful
for choosing the generated-code shape, but absolute timings will vary between
machines and PHP configurations.

## Competitor hydration

The competitor benchmark used correctly typed camelCase data containing five
fields. It measured both private promoted properties and ordinary public typed
properties without a constructor. Each operation created and fully hydrated a
new object. Dependency setup, metadata preparation, code generation, and warm-up
were excluded. Each round used nine samples of 20,000 objects, and the PHP 8.2
and PHP 8.5 rounds were alternated.

HydraType's recorded path includes its compiled missing-required-key protection.
Explicit `null` still enters normal coercion, while an absent required key fails
without producing a PHP warning or a cast default. Relative values use HydraType
as the 1.00x baseline.

Both PHP versions ran in images built from the same project Dockerfile on the
same Docker host. The images used Linux on ARM64, Xdebug 3.5.3 with all modes
disabled, and explicitly disabled CLI OPcache and JIT. The intended runtime
difference was PHP 8.2.32 versus PHP 8.5.8.

JoliCode AutoMapper 10 requires PHP 8.4 or newer, so it is excluded from the PHP
8.2 run. Its PHP Parser 5 dependency conflicts with GeneratedHydrator's PHP Parser
4 dependency; an isolated worker measures its resolved generated mapper with the
same operation count, warm-up, sampling, and verification while keeping mapper
resolution and process startup outside the timed region.

Crell Serde was measured through `SerdeCommon` using its `array` deserialization
format. One instance was reused and its in-memory analyzer was warmed before
measurement, so the result excludes metadata preparation while retaining the
public deserialization pipeline.

Each competitor used its resolved supported entry point and its dedicated
public-property path where one exists. In particular, Laminas used
`ObjectPropertyHydrator` instead of `ReflectionHydrator`, and Symfony's
`PropertyNormalizer` was limited to public visibility. Supported existing-object
alternatives were also measured for Patchlevel, Sunrise, Symfony, and JoliCode;
none was faster than the selected call. EventSauce is omitted from the
public-property result because its hydration model maps constructor parameters
rather than ordinary public properties.

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

HydraType was fastest for private properties in both environments. Ocramius
GeneratedHydrator was closest at 1.11x on PHP 8.2 and 1.19x on PHP 8.5.

### Public properties

The public-property values are averages of three round medians.

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

HydraType was fastest for public properties on PHP 8.5. On PHP 8.2, HydraType
and Ocramius were separated by about 1% and exchanged order between individual
rounds, which is effectively a tie at this measurement scale. Ocramius directly
assigns keys that are present and skips missing keys. HydraType additionally
checks required keys and coerces values to the declared property types.

## Batch hydration

The batch benchmark used the same correctly typed five-field private-property
fixture as the competitor benchmark. It compared repeated calls to the public
`HydraType::hydrate()` facade, its dedicated `hydrateMany()` path, and
handwritten PHP that passed cast array values directly to the constructor.
Unlike the competitor benchmark, this comparison includes facade dispatch and
retains every resulting object in an array so repeated `hydrate()` calls perform
the same consumer-visible work as `hydrateMany()`.

The cache was warmed before measurement. Each round used nine samples of
500,000 objects in batches of 1,000. The recorded values are averages of three
round medians from the standardized PHP 8.2 and PHP 8.5 Docker environments.

| Method                         |  PHP 8.2 | Relative |  PHP 8.5 | Relative |
|--------------------------------|---------:|---------:|---------:|---------:|
| Handwritten PHP                | 172.7 ns |    1.00x | 208.6 ns |    1.00x |
| HydraType `hydrateMany()`      | 211.5 ns |    1.22x | 242.8 ns |    1.16x |
| Repeated HydraType `hydrate()` | 336.3 ns |    1.95x | 343.6 ns |    1.65x |

`hydrateMany()` was 37.1% faster per object than repeated `hydrate()` calls on
PHP 8.2 and 29.3% faster on PHP 8.5. Compared with handwritten PHP, it added
22.5% and 16.4% respectively.

## Required-key access

The required-key benchmark compared generated expression shapes while creating
and assigning the same five-property public object. Every measured input
contained correctly typed, non-null values. Each result is the median
nanoseconds per object over 15 samples of 1,000,000 objects.

| Generated input path                 | PHP 8.2 | PHP 8.5 |
|--------------------------------------|--------:|--------:|
| Direct unchecked access              | 125.8 ns | 139.3 ns |
| One null-preserving `??` fallback    | 127.5 ns | 138.3 ns |
| Five null-preserving `??` fallbacks  | 131.2 ns | 144.0 ns |
| Five fallbacks plus naming selection | 135.3 ns | 148.4 ns |
| Five separate `array_key_exists()` guards | 165.7 ns | 191.8 ns |
| Five `array_key_exists()` ternaries  | 151.7 ns | 165.5 ns |

The selected `??` expression added approximately 5 ns per five-property object,
or about 1 ns per required property, while preserving explicit `null` through a
fallback lookup and throwing only for an absent key. The one camel/snake
selection added another 4.1–4.4 ns. Separate guards added approximately 40–53 ns,
and guarded ternaries added 26 ns. Required-property safety therefore uses the
coalescing expression directly in each generated conversion pipeline.

## Individual private-property writes

The first benchmark compared common ways to perform repeated writes to one private
property. The PHP 8.2 Docker result was:

| Method                                    | Median ns/write | Relative |
|-------------------------------------------|----------------:|---------:|
| In-class loop (lower bound)               |            8.02 |    1.00x |
| Bound closure with loop inside            |            9.62 |    1.20x |
| Public setter method                      |           25.64 |    3.20x |
| First-class setter callable               |           28.83 |    3.59x |
| Bound instance closure, invoked per write |           32.10 |    4.00x |
| Scoped static closure, invoked per write  |           36.89 |    4.60x |
| Cached `ReflectionProperty::setValue()`   |           39.27 |    4.90x |
| Magic `__set` method                      |           63.67 |    7.94x |
| `Closure::call()` per write               |           82.07 |   10.23x |
| Cached `ReflectionClass` property lookup  |           96.39 |   12.02x |
| New `ReflectionProperty` per write        |           97.96 |   12.21x |
| `Closure::bind()` and invoke per write    |          100.39 |   12.52x |

The relevant one-time setup costs were:

| Setup operation                        | Median ns |
|----------------------------------------|----------:|
| First-class setter callable            |     79.41 |
| New `ReflectionProperty`               |     85.10 |
| Bind static closure to class scope     |     93.75 |
| Bind instance closure to object        |     97.75 |
| New `ReflectionClass` and get property |    136.09 |

This established that closure binding is a meaningful fixed cost. A closure is
fast when assignments happen inside a single invocation, but binding or invoking a
closure separately for every property is expensive.

## Complete-object hydration

The property-count benchmark measured complete object allocation and straight-line
assignment for 1, 2, 3, 4, 5, 8, 10, 16, 20, and 32 private properties.

It compared:

- HydraType's current create-and-bind-per-object implementation;
- a cached closure template bound for every object;
- `Closure::call()` with a cached template;
- a static closure scoped once to the target class and passed each object;
- cached reflection properties in a loop; and
- generated, unrolled cached-reflection calls.

The closest comparison was between the pre-scoped static closure and generated,
unrolled cached reflection:

| Properties | PHP 8.2 static closure | PHP 8.2 reflection | PHP 8.5 static closure | PHP 8.5 reflection |
|-----------:|-----------------------:|-------------------:|-----------------------:|-------------------:|
|          1 |               91.44 ns |       **90.69 ns** |               72.27 ns |       **59.60 ns** |
|          2 |          **106.71 ns** |          146.19 ns |           **86.48 ns** |           94.29 ns |
|          3 |          **120.86 ns** |          192.49 ns |           **98.15 ns** |          181.99 ns |
|          5 |          **152.01 ns** |          289.91 ns |          **129.98 ns** |          212.75 ns |
|         10 |          **229.61 ns** |          536.10 ns |          **203.97 ns** |          378.42 ns |
|         20 |          **383.23 ns** |        1,028.87 ns |          **350.83 ns** |          742.07 ns |
|         32 |          **575.15 ns** |        1,666.75 ns |          **537.83 ns** |        1,192.58 ns |

The values are nanoseconds per complete object, including allocation.

At one property, unrolled reflection was faster by approximately 0.75 ns on PHP
8.2 and 12.67 ns on PHP 8.5. Reflection had the worse p95 on PHP 8.2 but retained
its advantage on PHP 8.5.

At two or more properties, the pre-scoped static closure won on both PHP versions.
Its advantage increased with the number of properties.

Compared with HydraType's current create-and-bind implementation, the pre-scoped
static closure reduced the PHP 8.2 median from 350.84 ns to 152.01 ns at five
properties, and from 687.14 ns to 383.23 ns at twenty properties.

## Generated hydrator A/B comparison

The final benchmark generated two complete hydrators from the same assignment
template. Both implementations used the same benchmark-local snake-case contract,
generated conversion maps, scalar casts, `ReflectionClass::newInstanceWithoutConstructor()`,
and the same input data.

The current implementation created and bound its writer closure for every object.
The candidate scoped one static writer in its constructor and reused it for every
object. Hydrator construction was measured separately and excluded from hydration
timings.

For PHP 8.2.32, the single-object and 10,000-row batch results were:

| Properties | Current `hydrate()` | Pre-scoped `hydrate()` |   Gain | Current batch | Pre-scoped batch |   Gain |
|-----------:|--------------------:|-----------------------:|-------:|--------------:|-----------------:|-------:|
|          1 |           481.98 ns |              338.08 ns | 29.86% |     270.20 ns |        128.97 ns | 52.27% |
|          5 |         1,168.58 ns |            1,000.36 ns | 14.39% |     410.23 ns |        236.74 ns | 42.29% |
|         10 |         2,065.74 ns |            1,852.50 ns | 10.32% |     592.98 ns |        381.37 ns | 35.69% |
|         20 |         3,934.94 ns |            3,639.79 ns |  7.50% |     951.53 ns |        664.78 ns | 30.14% |

For PHP 8.5.8, the same scenarios produced:

| Properties | Current `hydrate()` | Pre-scoped `hydrate()` |   Gain | Current batch | Pre-scoped batch |   Gain |
|-----------:|--------------------:|-----------------------:|-------:|--------------:|-----------------:|-------:|
|          1 |           454.73 ns |              340.72 ns | 25.07% |     211.31 ns |        111.15 ns | 47.40% |
|          5 |         1,092.43 ns |              947.16 ns | 13.30% |     326.80 ns |        203.84 ns | 37.63% |
|         10 |         1,872.14 ns |            1,708.18 ns |  8.76% |     462.63 ns |        316.68 ns | 31.55% |
|         20 |         3,484.41 ns |            3,258.13 ns |  6.49% |     749.88 ns |        551.94 ns | 26.40% |

Batch values are median nanoseconds per hydrated object. Intermediate batches of
1, 10, 100, and 1,000 rows were also measured. The candidate won at every tested
batch size and property count, and its p95 was generally better.

Constructing the pre-scoped hydrator cost approximately 264–299 ns, compared with
approximately 164–175 ns for the current hydrator. This one-time difference was
smaller than the first hydration saving in the recorded results and is amortized
when the hydrator is reused.

## Writer initialization

The writer-lifecycle benchmark compared three generated hydrator shapes:

- eager creation of both naming writers in the constructor;
- lazy creation through a writer accessor on every hydration; and
- inline lazy creation in `writerFor()`, with a creation method called only on a
  cache miss.

The principal median results relative to eager creation were:

| Scenario                            | PHP 8.2 accessor lazy | PHP 8.2 inline lazy | PHP 8.5 accessor lazy | PHP 8.5 inline lazy |
|-------------------------------------|----------------------:|--------------------:|----------------------:|--------------------:|
| Construct only                      |               -62.21% |         **-66.99%** |               -66.25% |         **-66.47%** |
| Construct and first camel hydration |               -14.11% |         **-16.54%** |               -10.62% |         **-13.32%** |
| Cached camel `hydrate()`            |                +6.45% |          **+0.15%** |               +12.95% |          **+0.40%** |
| Cached alternating `hydrate()`      |                +7.04% |          **+1.01%** |                +9.42% |          **+1.05%** |
| Cached `hydrateMany()`, batch 1,000 |                -1.44% |          **+0.02%** |                -0.22% |          **+0.32%** |

Negative percentages are faster than eager creation. Differences around one
percent varied between samples and are not treated as meaningful.

Lazy initialization through a separate accessor imposed a repeatable 6–13%
penalty on cached single-object hydration. Moving the null-coalescing property
check directly into `writerFor()` removed that penalty while retaining the
first-use saving. Inline lazy initialization therefore has eager-equivalent
steady-state performance and avoids creating a naming writer that is never used.

## Object creation

The object-creation benchmark compared direct construction, cached
`ReflectionClass::newInstanceWithoutConstructor()`, cached `ReflectionClass::newInstance()`,
cloning a constructor-bypassed prototype, uncached reflection, and `unserialize()`.
Targets with and without required constructors were measured with 0, 1, 5, 10,
20, and 50 defaulted private properties.

For classes without constructors, the principal median results were:

| Properties | PHP 8.2 direct `new` | PHP 8.2 cached reflection | PHP 8.5 direct `new` | PHP 8.5 cached reflection |
|-----------:|---------------------:|--------------------------:|---------------------:|--------------------------:|
|          0 |             31.37 ns |                  42.64 ns |             20.81 ns |                  25.10 ns |
|          1 |             33.01 ns |                  42.48 ns |             22.15 ns |                  26.80 ns |
|          5 |             35.29 ns |                  44.01 ns |             31.54 ns |                  36.16 ns |
|         10 |             37.95 ns |                  46.99 ns |             41.12 ns |                  45.70 ns |
|         20 |             43.68 ns |                  53.19 ns |             59.61 ns |                  64.95 ns |
|         50 |             81.11 ns |                  86.65 ns |            119.97 ns |                 124.58 ns |

Direct `new` was fastest at every tested property count. Its advantage over the
current cached reflection call ranged from approximately 6–26% on PHP 8.2 and
4–21% on PHP 8.5. Cached `ReflectionClass::newInstance()` was consistently slower
than `newInstanceWithoutConstructor()` even when the target had no constructor.

For classes whose required constructors had to be bypassed, prototype cloning won
through ten properties on PHP 8.2 but only through one property on PHP 8.5.
Cached reflection won above those crossover points. Cloning also changes behavior
for classes that define `__clone()`, so its version- and size-dependent advantage
does not support using it as the general creation strategy.

Uncached reflection and `unserialize()` were substantially slower on both PHP
versions. The results support direct generated construction only when reflection
confirms that the target class has no constructor. Cached
`newInstanceWithoutConstructor()` remains the general fallback.

## Writer as object factory

The writer-factory benchmark compared the current separation—create the object,
then pass it to the scoped writer—with a writer that creates, populates, and
returns the object itself. It covered 1, 5, 10, 20, and 50 properties, direct and
reflection-backed construction, single hydration, and batches from 1 to 1,000
objects.

On PHP 8.5, moving creation into the writer ranged from an approximately 5% gain
to an 8% regression, with most cases closer to parity. On PHP 8.2,
direct-construction batches sometimes improved by 2–6%, while single-object
hydration sometimes regressed and reflection-backed construction was generally
within 1% in either direction.

The result is too small and inconsistent to justify coupling object creation to
property assignment. HydraType should retain the current boundary: the hydrator
chooses and creates the target object, and the scoped writer only assigns its
properties. This preserves a natural extension point for alternate construction
without imposing a meaningful performance penalty on the default path.

## Value-map lookup

The value-map benchmark compared generated `match` expressions with inline
array lookup for maps containing 2, 4, 8, 16, 32, and 64 textual keys. Each
sample alternated between first, middle, last, and missing keys over 500,000
lookups.

| PHP | `match` median range | Array median range |
|-----|---------------------:|-------------------:|
| 8.2 |       25.00–32.29 ns |     35.02–36.14 ns |
| 8.5 |       32.77–36.75 ns |     56.84–59.12 ns |

`match` was faster for textual maps at every tested size on both PHP versions.
`MapValue` therefore emits `match` when all configured keys are non-empty
strings. Maps containing integer keys use guarded inline array lookup instead,
preserving PHP's numeric-string key behavior and passing non-keyable values
through at a small, explicitly selected cost.

## Nested hydration

The nested-hydration benchmark used equivalent data with five scalar leaves in
three shapes: one flat object, one nested level, and two nested levels. Child
hydrators were already resolved and all reader and writer closures were warm.
Batch measurements used 1,000 objects, and every value below is the median
nanoseconds per object over nine samples of 100,000 objects.

| PHP | Operation       |      Flat | One level | Two levels | One/flat | Two/flat |
|-----|-----------------|----------:|----------:|-----------:|---------:|---------:|
| 8.2 | `hydrate()`     | 273.52 ns | 452.61 ns |  632.16 ns |    1.65x |    2.31x |
| 8.2 | `hydrateMany()` | 209.80 ns | 387.70 ns |  563.68 ns |    1.85x |    2.69x |
| 8.2 | `extract()`     | 158.72 ns | 261.36 ns |  364.51 ns |    1.65x |    2.30x |
| 8.2 | `extractMany()` | 107.43 ns | 212.90 ns |  320.23 ns |    1.98x |    2.98x |
| 8.5 | `hydrate()`     | 295.13 ns | 465.49 ns |  630.15 ns |    1.58x |    2.14x |
| 8.5 | `hydrateMany()` | 241.05 ns | 411.38 ns |  578.09 ns |    1.71x |    2.40x |
| 8.5 | `extract()`     | 160.34 ns | 267.24 ns |  368.45 ns |    1.67x |    2.30x |
| 8.5 | `extractMany()` | 105.61 ns | 210.14 ns |  317.62 ns |    1.99x |    3.01x |

Each selected level adds the allocation and compiled reader or writer call for
one real child object. Single extraction selects its naming reader inline, while
batch APIs select the parent reader or writer once. Each child object still
requires its own generated hydrator call. The cost therefore grows with actual
object depth and remains absent from the flat generated property path. This
confirms the selected composition model: resolve each child hydrator once,
capture it in the parent closure, and pay only for nested objects that are
present in the class shape.

## Compiled assertions

The assertion benchmark hydrated equivalent five-integer objects with no
assertions, one successful assertion, or three successful assertions on one
property. Writers were warm, batch measurements used 1,000 objects, and every
value below is the median nanoseconds per object over nine samples of 100,000
objects.

| PHP | Operation       |      None |       One |     Three | One/none | Three/none |
|-----|-----------------|----------:|----------:|----------:|---------:|-----------:|
| 8.2 | `hydrate()`     | 239.46 ns | 244.03 ns | 246.91 ns |    1.02x |      1.03x |
| 8.2 | `hydrateMany()` | 198.64 ns | 193.29 ns | 198.92 ns |    0.97x |      1.00x |
| 8.5 | `hydrate()`     | 234.88 ns | 249.94 ns | 264.74 ns |    1.06x |      1.13x |
| 8.5 | `hydrateMany()` | 186.38 ns | 196.02 ns | 209.93 ns |    1.05x |      1.13x |

Successful inline assertions added a small, selected cost. The PHP 8.2 batch
differences were within measurement variance; PHP 8.5 measured about 10 ns for
one batch assertion and 24 ns for three. The assertion-free fixture retained the
same direct generated assignments, so classes and properties that do not select
assertions gained no runtime work.

## Cache modes

The cache-mode benchmark measured first hydrator resolution from an existing
disk cache and repeated resolution from one factory. Compilation was not part of
the measurement. Each result is the median nanoseconds per resolution over nine
samples of 10,000 resolutions.

| Resolution path            |      PHP 8.2 |     PHP 8.5 |
|----------------------------|-------------:|------------:|
| Automatic first resolution | 274,826.1 ns | 41,897.1 ns |
| Read-only first resolution |   5,960.0 ns |  8,620.4 ns |
| In-memory resolution       |      65.1 ns |     70.9 ns |

Automatic mode verifies the embedded dependency fingerprint by reading the
generated header and the source contents once per first factory resolution. The
PHP 8.2 Docker run used bind-mounted macOS files, where those reads were notably
more expensive than the PHP 8.5 host run. Read-only mode skipped the verification
and reduced first-resolution time by approximately 98% and 79%, respectively.
Neither mode alters repeated in-memory resolution or generated hydration
performance.

## Cold compilation

The cold-compilation benchmark used a ten-property target containing scalar
conversion, mutators, an assertion, JSON, a date, and nested hydration. It
measured each compilation stage independently. The final implementation recorded:

| Stage                        | PHP 8.2 Docker | PHP 8.5 host |
|------------------------------|---------------:|-------------:|
| Analyze class                |       20.25 µs |     21.50 µs |
| Initialize compiler          |       24.58 µs |     27.10 µs |
| Discover dependencies        |        3.21 µs |      3.97 µs |
| Fingerprint sources          |      987.83 µs |     88.33 µs |
| Generate source              |    1,161.59 µs |    302.74 µs |
| Parse generated source       |       97.28 µs |    101.84 µs |
| Atomically publish source    |      844.23 µs |    330.93 µs |
| Compile one target           |    1,951.71 µs |    782.67 µs |
| Warm target and nested graph |    4,244.19 µs |  1,447.50 µs |

The PHP 8.2 Docker image reads the project through a bind-mounted macOS
filesystem. Content hashing and atomic publication therefore dominate its cold
path and vary more than compiler CPU work. These costs protect dependable cache
invalidation and publication, so they remain intact.

An earlier PHP 8.4 optimization run showed that normalizing each property's
reflection facts, attributes, and camel/snake names once reduced source generation
from 373.51 to 319.31 µs, complete target compilation from 882.62 to 814.43 µs,
and graph warm-up from 1,675.82 to 1,568.69 µs. The PHP 8.2 complete-target median
improved by about 5%, while graph warm-up remained within filesystem variance.
Further cache machinery is not justified: cold compilation stays straightforward,
and the dominant safety work remains explicit in the measurements.
