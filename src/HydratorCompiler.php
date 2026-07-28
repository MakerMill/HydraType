<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\Compiler\ExtractionTypeConverters\ExtractionTypeConverterCompiler;
use MakerMill\HydraType\Compiler\PhpCodeBuilder;
use MakerMill\HydraType\Compiler\TypeConverters\TypeConverterCompiler;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Interfaces\AssertionInterface;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Mutators\HydrateAs;
use ParseError;
use ReflectionClass;

/**
 * Converts analyzed class metadata into a specialized PHP hydrator.
 *
 * Reflection, naming, conversion, mutator, and nested-target decisions happen here. The result is a complete PHP class
 * whose repeated runtime paths contain direct property access rather than metadata inspection or extension dispatch.
 * Optional features alter only hydrators that select them; they must not add work to an unrelated generated path.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class HydratorCompiler
{
    public const CACHE_VERSION = 4;

    /**
     * @var ClassAnalyzer<T>
     */
    private ClassAnalyzer $classAnalyzer;
    private ExtractionTypeConverterCompiler $extractionTypeConverterCompiler;
    private TypeConverterCompiler $typeConverterCompiler;
    /** @var array<string, class-string> Property name to the concrete class used for that nested value. */
    private array $nestedHydrationTargets;
    /** @var array<string, class-string> Property name to its explicitly configured nested target. */
    private array $explicitNestedHydrationTargets;
    /** @var array<class-string, string> Concrete class to its shared variable in generated closures. */
    private array $nestedHydratorVariables;

    /**
     * @param \MakerMill\HydraType\ClassDescriptor<T> $classDescriptor
     * @param \MakerMill\HydraType\Configuration      $configuration
     */
    public function __construct(private ClassDescriptor $classDescriptor, private Configuration $configuration)
    {
        // Analyze and reject unsupported shapes before source generation, so no partial or knowingly invalid cache
        // entry can be published. The resulting metadata is then shared by all four naming/direction paths.
        $this->classAnalyzer = new ClassAnalyzer($classDescriptor);
        $classValidator = new ClassValidator($this->classAnalyzer);
        $classValidator->validate();
        $this->extractionTypeConverterCompiler = new ExtractionTypeConverterCompiler();
        $this->typeConverterCompiler = new TypeConverterCompiler();
        [$this->nestedHydrationTargets, $this->explicitNestedHydrationTargets] =
            $this->analyzeNestedHydrationTargets();
        $this->nestedHydratorVariables = $this->buildNestedHydratorVariables($this->nestedHydrationTargets);
    }

    /**
     * Exposes the same nested graph used by generated code so cache warm-up cannot drift from compiler behavior.
     *
     * @return list<class-string>
     */
    public function getNestedClassNames(): array
    {
        return array_keys($this->nestedHydratorVariables);
    }

    public function compile(bool $force = false): void
    {
        // HydratorCacheFile owns freshness checks, locking, and atomic publication; this class only supplies source when
        // compilation is actually required.
        $cacheFile = new HydratorCacheFile($this->classDescriptor);
        $cacheFile->compile(fn (): string => $this->generateCode(), $force);
    }

    private function generateCode(): string
    {
        // Separate naming writers are safe only when each generated input key identifies exactly one property.
        $this->assertUnambiguousInputKeys();

        $namespace = $this->configuration->getHydratorNamespace();
        $hydratorName = $this->classDescriptor->getShortHydratorClassName();

        $className = $this->classDescriptor->getShortClassName();
        $fqClassName = $this->classDescriptor->getFQClassName();
        $hasConstructor = $this->classAnalyzer->hasConstructor();
        $hasNestedHydration = $this->nestedHydratorVariables !== [];
        $usesDirectHydration = !$hasNestedHydration
            && $this->classAnalyzer->hasOnlyPubliclyWritableProperties();

        // Plain construction is faster, but any constructor is bypassed to avoid arguments, side effects, and invariants.
        $objectCreation = $hasConstructor
            ? '$this->reflectionClass->newInstanceWithoutConstructor()'
            : "new {$className}()";
        $caughtExceptions = $hasConstructor ? '\\ReflectionException|\\TypeError' : '\\TypeError';

        // Generate complete camelCase and snake_case paths once. Runtime selects a precompiled path instead of
        // converting every property name for every hydrated or extracted object.
        $camelHydrationCode = $this->generateHydrationCode(false);
        $snakeHydrationCode = $this->generateHydrationCode(true);
        $camelExtractionCode = $this->generateExtractionCode(false);
        $snakeExtractionCode = $this->generateExtractionCode(true);
        $writerSelectionCode = $this->generateWriterSelectionCode();

        // The header records source that influenced compilation, allowing Auto mode to reject stale executable PHP
        // without placing any fingerprint work in the generated hydration path.
        $cacheHeader = CacheFingerprint::header($this->classAnalyzer->getCacheDependencies());

        $code = $this->generateSource(
            $cacheHeader,
            $namespace,
            $hydratorName,
            $className,
            $fqClassName,
            $hasConstructor,
            $hasNestedHydration,
            $usesDirectHydration,
            $objectCreation,
            $caughtExceptions,
            $camelHydrationCode,
            $snakeHydrationCode,
            $camelExtractionCode,
            $snakeExtractionCode,
            $writerSelectionCode,
        );
        // Parse the complete result before HydratorCacheFile atomically publishes it to a cache shared by processes.
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $error) {
            // Consumer extensions emit PHP expressions, so report malformed output through HydraType's exception
            // boundary while preserving the engine diagnostic for debugging the extension.
            throw HydrationException::forGeneratedCodeParseError(
                $this->classDescriptor->getClassName(),
                $error,
            );
        }
        if ($tokens === []) {
            throw HydrationException::forInvalidClass($hydratorName);
        }

        return $code;
    }

    private function generateSource(
        string $cacheHeader,
        string $namespace,
        string $hydratorName,
        string $className,
        string $fqClassName,
        bool $hasConstructor,
        bool $hasNestedHydration,
        bool $usesDirectHydration,
        string $objectCreation,
        string $caughtExceptions,
        string $camelHydrationCode,
        string $snakeHydrationCode,
        string $camelExtractionCode,
        string $snakeExtractionCode,
        string $writerSelectionCode,
    ): string {
        $code = new PhpCodeBuilder();
        $code->line('<?php');
        $code->line($cacheHeader);
        $code->line();
        $code->line('declare(strict_types=1);');
        $code->line();
        $code->line("namespace {$namespace};");
        $code->line();
        $code->line('use Closure;');
        $code->line('use MakerMill\\HydraType\\HydrationException\\HydrationException;');
        $code->line('use MakerMill\\HydraType\\Interfaces\\HydratorInterface;');
        $code->line('use MakerMill\\HydraType\\NamingConvention;');
        // Only nested hydrators receive the factory dependency and marker interface used to construct them. Keeping
        // these declarations conditional preserves the scalar-only generated class shape.
        if ($hasNestedHydration) {
            $code->line('use MakerMill\\HydraType\\HydratorFactory;');
            $code->line('use MakerMill\\HydraType\\Interfaces\\FactoryAwareHydratorInterface;');
        }
        if ($hasConstructor) {
            $code->line('use ReflectionClass;');
        }
        $code->line('use ' . ltrim($fqClassName, '\\') . ';');
        $code->line();
        $interfaces = $hasNestedHydration
            ? 'FactoryAwareHydratorInterface'
            : 'HydratorInterface';
        $implementedInterface = $hasNestedHydration
            ? 'FactoryAwareHydratorInterface'
            : 'HydratorInterface';
        $code->line("/** @implements {$implementedInterface}<{$className}> */");
        $code->open("final class {$hydratorName} implements {$interfaces}");
        if ($hasConstructor) {
            $code->line("/** @var \\ReflectionClass<{$className}> */");
            $code->line('private ReflectionClass $reflectionClass;');
        }
        if ($hasNestedHydration) {
            $code->line('private HydratorFactory $hydratorFactory;');
            foreach ($this->nestedHydratorVariables as $nestedClassName => $variable) {
                $code->line("/** @var ?HydratorInterface<\\{$nestedClassName}> */");
                $code->line("private ?HydratorInterface \${$variable} = null;");
            }
        }
        if (!$usesDirectHydration) {
            // Non-public state needs one class-scoped writer per selected naming path.
            $code->line('private ?Closure $camelWriter = null;');
            $code->line('private ?Closure $snakeWriter = null;');
        }
        // Naming-specific readers are created only if requested and reused for every later extraction on that path.
        $code->line("/** @var (Closure({$className}): array<string, mixed>)|null */");
        $code->line('private ?Closure $camelReader = null;');
        $code->line("/** @var (Closure({$className}): array<string, mixed>)|null */");
        $code->line('private ?Closure $snakeReader = null;');
        $code->line();

        if ($hasConstructor || $hasNestedHydration) {
            // The generated constructor contains only reusable infrastructure. Target constructors are never called.
            $constructor = $hasNestedHydration
                ? 'public function __construct(HydratorFactory $hydratorFactory)'
                : 'public function __construct()';
            $code->open($constructor);
            if ($hasNestedHydration) {
                $code->line('$this->hydratorFactory = $hydratorFactory;');
            }
            if ($hasConstructor) {
                $code->line("\$this->reflectionClass = new ReflectionClass({$className}::class);");
            }
            $code->close();
            $code->line();
        }

        if (!$usesDirectHydration) {
            $this->appendWriterMethod($code, 'createCamelWriter', $className, $camelHydrationCode);
            $this->appendWriterMethod($code, 'createSnakeWriter', $className, $snakeHydrationCode);
        }
        $this->appendReaderMethod($code, 'createCamelReader', $className, $camelExtractionCode);
        $this->appendReaderMethod($code, 'createSnakeReader', $className, $snakeExtractionCode);

        if (!$usesDirectHydration) {
            // Hydration infers the naming convention from a distinguishing input key and memoizes its scoped writer.
            $code->line('/** @param array<string, mixed> $data */');
            $code->open('private function writerFor(array $data): Closure');
            $code->code($writerSelectionCode);
            $code->close();
            $code->line();
        }

        // Preserve the callable's array result for static analysis after it is selected through the generic Closure
        // properties. Without this generated PHPDoc, invoking the reader is inferred as mixed.
        $code->line("/** @return Closure({$className}): array<string, mixed> */");
        $code->open('private function readerFor(NamingConvention $namingConvention): Closure');
        $code->openInline('if ($namingConvention === NamingConvention::SnakeCase)');
        $code->line('return $this->snakeReader ??= $this->createSnakeReader();');
        $code->close();
        $code->line('return $this->camelReader ??= $this->createCamelReader();');
        $code->close();
        $code->line();

        $code->line('/**');
        $code->line(' * @param array<string, mixed> $data');
        $code->line(' *');
        $code->line(" * @return {$fqClassName}");
        $code->line(' */');
        $code->open("public function hydrate(array \$data): {$className}");
        if (!$this->classAnalyzer->allowsEmptyData()) {
            $code->openInline('if (empty($data))');
            $code->line("throw HydrationException::forEmptyData({$className}::class);");
            $code->close();
        }
        if (!$usesDirectHydration) {
            $code->line('$writer = $this->writerFor($data);');
        }
        $code->openInline('try');
        $code->line("/** @var {$className} \$object */");
        $code->line("\$object = {$objectCreation};");
        if ($usesDirectHydration) {
            $this->appendDirectHydrationCode(
                $code,
                $camelHydrationCode,
                $snakeHydrationCode,
                $this->generateSnakeCaseSelectionExpression('$data'),
            );
        } else {
            $code->line('$writer($object, $data);');
        }
        $code->line('return $object;');
        $code->close(" catch ({$caughtExceptions} \$e) {");
        $code->indent();
        $code->line("throw HydrationException::forHydrationError({$className}::class, \$e);");
        $code->outdent();
        $code->line('}');
        $code->close();
        $code->line();

        $code->line('/**');
        $code->line(' * @param array<int, array<string, mixed>> $dataSet');
        $code->line(' *');
        $code->line(" * @return array<int, {$className}>");
        $code->line(' */');
        $code->open('public function hydrateMany(array $dataSet): array');
        $code->openInline('if (empty($dataSet))');
        $code->line("throw HydrationException::forEmptyData({$className}::class);");
        $code->close();
        $code->line('$firstData = $dataSet[array_key_first($dataSet)];');
        // A batch is required to use one naming convention, so selection happens once outside its object loop.
        if ($usesDirectHydration && $camelHydrationCode !== $snakeHydrationCode) {
            $selectionExpression = $this->generateSnakeCaseSelectionExpression('$firstData');
            $code->line("\$snakeCase = {$selectionExpression};");
        } elseif (!$usesDirectHydration) {
            $code->line('$writer = $this->writerFor($firstData);');
        }
        $code->line('$results = [];');
        $code->openInline('foreach ($dataSet as $data)');
        $code->openInline('try');
        $code->line("/** @var {$className} \$object */");
        $code->line("\$object = {$objectCreation};");
        if ($usesDirectHydration) {
            $this->appendDirectHydrationCode($code, $camelHydrationCode, $snakeHydrationCode, '$snakeCase');
        } else {
            $code->line('$writer($object, $data);');
        }
        $code->line('$results[] = $object;');
        $code->close(" catch ({$caughtExceptions} \$e) {");
        $code->indent();
        $code->line("throw HydrationException::forHydrationError({$className}::class, \$e);");
        $code->outdent();
        $code->line('}');
        $code->close();
        $code->line('return $results;');
        $code->close();
        $code->line();

        $code->line('/**');
        $code->line(" * @param {$className} \$object");
        $code->line(' *');
        $code->line(' * @return array<string, mixed>');
        $code->line(' */');
        $code->line('public function extract(');
        $code->indent();
        $code->line('object $object,');
        $code->line('NamingConvention $namingConvention = NamingConvention::CamelCase,');
        $code->outdent();
        $code->open('): array');
        // Single extraction cannot amortize readerFor(), so inline its branch while retaining lazy closure creation.
        $code->openInline('if ($namingConvention === NamingConvention::SnakeCase)');
        $code->line('return ($this->snakeReader ??= $this->createSnakeReader())($object);');
        $code->close();
        $code->line('return ($this->camelReader ??= $this->createCamelReader())($object);');
        $code->close();
        $code->line();

        $code->line('/**');
        $code->line(" * @param array<int, {$className}> \$objects");
        $code->line(' *');
        $code->line(' * @return array<int, array<string, mixed>>');
        $code->line(' */');
        $code->line('public function extractMany(');
        $code->indent();
        $code->line('array $objects,');
        $code->line('NamingConvention $namingConvention = NamingConvention::CamelCase,');
        $code->outdent();
        $code->open('): array');
        $code->openInline('if ($objects === [])');
        $code->line('return [];');
        $code->close();
        // As with hydration, extraction resolves its naming-specific closure once for the complete batch.
        $code->line('$reader = $this->readerFor($namingConvention);');
        $code->line('$results = [];');
        $code->openInline('foreach ($objects as $object)');
        $code->line('$results[] = $reader($object);');
        $code->close();
        $code->line('return $results;');
        $code->close();
        $code->close();

        return $code->build();
    }

    private function appendDirectHydrationCode(
        PhpCodeBuilder $code,
        string $camelHydrationCode,
        string $snakeHydrationCode,
        string $selectionExpression,
    ): void {
        if ($camelHydrationCode === $snakeHydrationCode) {
            $code->code($camelHydrationCode);
            return;
        }

        // Public writable properties need no class scope. Keep both naming paths in the generated method so assignment
        // avoids a closure invocation, while the one convention branch replaces the selected writer call.
        $code->openInline("if ({$selectionExpression})");
        $code->code($snakeHydrationCode);
        $code->close(' else {');
        $code->indent();
        $code->code($camelHydrationCode);
        $code->close();
    }

    private function appendWriterMethod(
        PhpCodeBuilder $code,
        string $methodName,
        string $className,
        string $body,
    ): void {
        // Child hydrators are resolved while the writer is created and captured by the closure. Neither the factory nor
        // the child lookup is therefore present in an individual object's assignment path.
        $code->open("private function {$methodName}(): Closure");
        $this->appendNestedHydratorResolutions($code);
        // Binding once to target-class scope gives direct access to non-public properties without per-write reflection.
        $code->line('return Closure::bind(');
        $code->indent();
        $code->openInline(
            "static function ({$className} \$object, array \$data)"
            . $this->nestedHydratorUseClause()
            . ': void',
        );
        $code->code($body);
        $code->close(',');
        $code->line('null,');
        $code->line("{$className}::class,");
        $code->outdent();
        $code->line(');');
        $code->close();
        $code->line();
    }

    private function appendReaderMethod(
        PhpCodeBuilder $code,
        string $methodName,
        string $className,
        string $body,
    ): void {
        // Readers use the same one-time child resolution and class-scoped access strategy as writers.
        $code->line("/** @return Closure({$className}): array<string, mixed> */");
        $code->open("private function {$methodName}(): Closure");
        $this->appendNestedHydratorResolutions($code);
        $code->line('return Closure::bind(');
        $code->indent();
        $code->openInline(
            "static function ({$className} \$object)"
            . $this->nestedHydratorUseClause()
            . ': array',
        );
        $code->code($body);
        $code->close(',');
        $code->line('null,');
        $code->line("{$className}::class,");
        $code->outdent();
        $code->line(');');
        $code->close();
        $code->line();
    }

    private function generateHydrationCode(bool $snakeCase): string
    {
        $lines = [];
        foreach ($this->classAnalyzer->getProperties() as $property) {
            $propertyName = $property->getName();
            $inputKey = $snakeCase ? $property->getSnakeCaseName() : $property->getCamelCaseName();
            $input = '$data[' . var_export($inputKey, true) . ']';
            $sourceInput = $input;
            $input = $this->compileRequiredInput($property, $inputKey, $input);

            // Build one expression in pipeline order: selected attributes, inferred nested hydration, then the cheapest
            // built-in conversion required by the declared property type.
            [$input, $inputType, $nestedHydrationApplied] = $this->compileExplicitMutators($property, $input);
            if (isset($this->nestedHydrationTargets[$propertyName]) && !$nestedHydrationApplied) {
                $input = $this->compileNestedHydration(
                    $input,
                    $this->nestedHydrationTargets[$propertyName],
                );
                $inputType = $property->getType();
            }
            $value = $this->typeConverterCompiler->compile($property, $input, $inputType);
            if ($value === null) {
                throw HydrationException::forUnsupportedType(
                    $this->classDescriptor->getClassName(),
                    $propertyName,
                    $property->getType(),
                );
            }

            // Guard the original value so mutators are never asked to interpret missing or null input.
            if ($property->allowsNull()) {
                $value = "isset({$sourceInput}) ? {$value} : null";
            }

            $propertyLines = $this->compilePropertyAssignment($property, $value);
            if ($property->isOptional()) {
                // Declared property defaults survive constructor bypass. Promoted defaults do not, so reproduce that
                // constructor assignment only for the optional property that selected this behavior.
                $lines[] = 'if (array_key_exists(' . var_export($inputKey, true) . ', $data)) {';
                foreach ($propertyLines as $propertyLine) {
                    $lines[] = '    ' . $propertyLine;
                }
                if ($property->defaultRequiresAssignment()) {
                    $lines[] = '} else {';
                    $lines[] = '    $object->' . $propertyName . ' = '
                        . $this->compileDefaultValue($property->getDefaultValue(), $propertyName) . ';';
                }
                $lines[] = '}';
                continue;
            }
            array_push($lines, ...$propertyLines);
        }

        return implode("\n", $lines);
    }

    private function compileRequiredInput(
        PropertyAnalyzer $property,
        string $inputKey,
        string $inputExpression,
    ): string {
        if ($property->allowsNull() || $property->isOptional()) {
            return $inputExpression;
        }

        // The coalesce path fetches a normal non-null value once. Only explicit null needs the fallback lookup that
        // distinguishes a present null, which retains normal coercion, from an absent key, which must fail cleanly.
        return '(' . $inputExpression . ' ?? (array_key_exists(' . var_export($inputKey, true) . ', $data)'
            . ' ? null : throw HydrationException::forMissingRequiredProperty('
            . $this->classDescriptor->getFQClassName() . '::class, '
            . var_export($property->getName(), true) . ')))';
    }

    private function compileDefaultValue(mixed $value, string $propertyName): string
    {
        if (is_string($value)) {
            return PhpLiteral::string($value);
        }
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return var_export($value, true);
        }
        if ($value instanceof \UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }
        if (is_array($value)) {
            $entries = [];
            foreach ($value as $key => $entry) {
                $keyExpression = is_string($key) ? PhpLiteral::string($key) : (string) $key;
                $entries[] = $keyExpression . ' => ' . $this->compileDefaultValue($entry, $propertyName);
            }

            return '[' . implode(', ', $entries) . ']';
        }

        throw HydrationException::forUnsupportedPromotedDefault(
            $this->classDescriptor->getClassName(),
            $propertyName,
            get_debug_type($value),
        );
    }

    /**
     * @return list<string>
     */
    private function compilePropertyAssignment(PropertyAnalyzer $property, string $valueExpression): array
    {
        $assertions = [];
        foreach ($property->getAttributes() as $attribute) {
            if (!is_a($attribute->getName(), AssertionInterface::class, true)) {
                continue;
            }

            $assertion = $attribute->newInstance();
            if ($assertion instanceof AssertionInterface) {
                $assertions[] = [$attribute->getName(), $assertion];
            }
        }

        $propertyName = $property->getName();
        if ($assertions === []) {
            return ["\$object->{$propertyName} = {$valueExpression};"];
        }

        // An asserted property evaluates its complete mutation/conversion expression once. Plain properties never gain
        // this temporary assignment, and multiple assertions reuse the one selected value.
        $valueVariable = '$hydraAssertionValue';
        $lines = ["{$valueVariable} = {$valueExpression};"];
        foreach ($assertions as [$assertionClass, $assertion]) {
            $condition = $assertion->compileCondition($valueVariable);
            if ($property->allowsNull()) {
                $condition = "{$valueVariable} === null || ({$condition})";
            }
            $lines[] = "if (!({$condition})) {";
            $lines[] = '    throw \\MakerMill\\HydraType\\HydrationException\\AssertionException::forProperty(';
            $lines[] = '        ' . $this->classDescriptor->getFQClassName() . '::class,';
            $lines[] = '        ' . PhpLiteral::string($propertyName) . ',';
            $lines[] = '        \\' . ltrim($assertionClass, '\\') . '::class,';
            $lines[] = '        ' . PhpLiteral::string($assertion->message()) . ',';
            $lines[] = '    );';
            $lines[] = '}';
        }
        $lines[] = "\$object->{$propertyName} = {$valueVariable};";

        return $lines;
    }

    private function generateExtractionCode(bool $snakeCase): string
    {
        $entries = [];
        foreach ($this->classAnalyzer->getProperties() as $property) {
            $propertyName = $property->getName();
            $outputKey = $snakeCase ? $property->getSnakeCaseName() : $property->getCamelCaseName();
            $input = "\$object->{$propertyName}";
            $value = $this->extractionTypeConverterCompiler->compile($property, $input);
            $hasValueTransformation = false;
            // Automatic nesting is the final hydration step, so it is the first step unwound during extraction.
            if (
                isset($this->nestedHydrationTargets[$propertyName])
                && !isset($this->explicitNestedHydrationTargets[$propertyName])
            ) {
                $value = $this->compileNestedExtraction(
                    $value,
                    $this->nestedHydrationTargets[$propertyName],
                    $snakeCase,
                );
                $hasValueTransformation = true;
            }
            [$value, $hasExplicitTransformation] = $this->compileExplicitExtractionMutators(
                $property,
                $value,
                $snakeCase,
            );
            // Plain nullable values can be read directly. A transformed value needs a guard so generated calls never
            // receive null unless the selected transformation explicitly produced it.
            if (($hasValueTransformation || $hasExplicitTransformation) && $property->allowsNull()) {
                $value = "isset({$input}) ? {$value} : null";
            }
            $entries[] = var_export($outputKey, true) . " => {$value}";
        }

        return 'return [' . implode(', ', $entries) . '];';
    }

    private function generateWriterSelectionCode(): string
    {
        // Inspect only keys whose camel and snake spellings differ. The first match chooses the naming-specific writer;
        // its closure is cached, while repeating this small scan lets one hydrator accept either convention over time.
        $lines = [];
        foreach ($this->classAnalyzer->getProperties() as $property) {
            $propertyName = $property->getName();
            $camelKey = $property->getCamelCaseName();
            $snakeKey = $property->getSnakeCaseName();
            if ($camelKey === $snakeKey) {
                continue;
            }

            $lines[] = 'if (array_key_exists(' . var_export($camelKey, true) . ', $data)) {';
            $lines[] = '    return $this->camelWriter ??= $this->createCamelWriter();';
            $lines[] = '}';
            $lines[] = 'if (array_key_exists(' . var_export($snakeKey, true) . ', $data)) {';
            $lines[] = '    return $this->snakeWriter ??= $this->createSnakeWriter();';
            $lines[] = '}';
        }
        $lines[] = 'return $this->camelWriter ??= $this->createCamelWriter();';

        return implode("\n", $lines);
    }

    private function generateSnakeCaseSelectionExpression(string $dataExpression): string
    {
        // A required non-null property must be present in every supported, consistently named input. Its snake
        // spelling therefore distinguishes the convention with one lookup and avoids a redundant camel-key check.
        foreach ($this->classAnalyzer->getProperties() as $property) {
            $camelKey = $property->getCamelCaseName();
            $snakeKey = $property->getSnakeCaseName();
            if (
                $camelKey !== $snakeKey
                && !$property->isOptional()
                && !$property->allowsNull()
            ) {
                return 'array_key_exists(' . var_export($snakeKey, true) . ", {$dataExpression})";
            }
        }

        // Build the direct-path equivalent of writerFor() in reverse so the outermost condition preserves the original
        // property order and its camel-before-snake precedence when no always-present discriminator is available.
        $expression = 'false';
        foreach (array_reverse($this->classAnalyzer->getProperties()) as $property) {
            $camelKey = $property->getCamelCaseName();
            $snakeKey = $property->getSnakeCaseName();
            if ($camelKey === $snakeKey) {
                continue;
            }

            $camelLookup = 'array_key_exists(' . var_export($camelKey, true) . ", {$dataExpression})";
            $snakeLookup = 'array_key_exists(' . var_export($snakeKey, true) . ", {$dataExpression})";
            $expression = "{$camelLookup} ? false : ({$snakeLookup} ? true : ({$expression}))";
        }

        return $expression;
    }

    private function assertUnambiguousInputKeys(): void
    {
        // Generated writers cannot perform validation or runtime mapping without compromising their direct paths.
        // Reject collisions now rather than silently assigning one input key to multiple properties.
        $camelKeys = [];
        $snakeKeys = [];

        foreach ($this->classAnalyzer->getProperties() as $property) {
            $propertyName = $property->getName();
            $camelKey = $property->getCamelCaseName();
            $snakeKey = $property->getSnakeCaseName();

            if (isset($camelKeys[$camelKey]) && $camelKeys[$camelKey] !== $propertyName) {
                throw HydrationException::forAmbiguousInputKey(
                    $this->classDescriptor->getClassName(),
                    $camelKey,
                    $camelKeys[$camelKey],
                    $propertyName,
                );
            }
            if (isset($snakeKeys[$snakeKey]) && $snakeKeys[$snakeKey] !== $propertyName) {
                throw HydrationException::forAmbiguousInputKey(
                    $this->classDescriptor->getClassName(),
                    $snakeKey,
                    $snakeKeys[$snakeKey],
                    $propertyName,
                );
            }

            $camelKeys[$camelKey] = $propertyName;
            $snakeKeys[$snakeKey] = $propertyName;
        }
    }

    /** @return array{string, string|null, bool} */
    private function compileExplicitMutators(PropertyAnalyzer $property, string $inputExpression): array
    {
        // Attribute order is hydration order: each selected transformation wraps the expression produced before it.
        // HydrateAs participates at its declaration position rather than being forced to the end of the pipeline.
        $inputType = null;
        $nestedHydrationApplied = false;
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() === HydrateAs::class) {
                $hydrateAs = $attribute->newInstance();
                if (!$hydrateAs instanceof HydrateAs) {
                    continue;
                }

                $inputExpression = $this->compileNestedHydration($inputExpression, $hydrateAs->className());
                // Validation established assignability, so the normal converter can omit an unnecessary final cast.
                $inputType = $property->getType();
                $nestedHydrationApplied = true;
                continue;
            }

            if (!is_a($attribute->getName(), MutatorInterface::class, true)) {
                continue;
            }

            $mutator = $attribute->newInstance();
            if (!$mutator instanceof MutatorInterface) {
                continue;
            }

            $inputExpression = $mutator->compile($inputExpression);
            $inputType = $mutator->outputType();
        }

        return [$inputExpression, $inputType, $nestedHydrationApplied];
    }

    /** @return array{string, bool} */
    private function compileExplicitExtractionMutators(
        PropertyAnalyzer $property,
        string $inputExpression,
        bool $snakeCase,
    ): array {
        // Extraction reverses declaration order so bidirectional transformations unwind the hydration expression. An
        // explicit HydrateAs is reversed at the same point, which allows compositions such as JsonValue then HydrateAs.
        $hasExplicitTransformation = false;
        foreach (array_reverse($property->getAttributes()) as $attribute) {
            if ($attribute->getName() === HydrateAs::class) {
                $hydrateAs = $attribute->newInstance();
                if (!$hydrateAs instanceof HydrateAs) {
                    continue;
                }

                $inputExpression = $this->compileNestedExtraction(
                    $inputExpression,
                    $hydrateAs->className(),
                    $snakeCase,
                );
                $hasExplicitTransformation = true;
                continue;
            }

            if (!is_a($attribute->getName(), ExtractionMutatorInterface::class, true)) {
                continue;
            }

            $mutator = $attribute->newInstance();
            if (!$mutator instanceof ExtractionMutatorInterface) {
                continue;
            }

            $inputExpression = $mutator->compileExtraction($inputExpression);
            $hasExplicitTransformation = true;
        }

        return [$inputExpression, $hasExplicitTransformation];
    }

    /**
     * @return array{
     *     array<string, class-string>,
     *     array<string, class-string>
     * }
     */
    private function analyzeNestedHydrationTargets(): array
    {
        $targets = [];
        $explicitTargets = [];
        foreach ($this->classAnalyzer->getProperties() as $property) {
            // Explicit selection wins because it is required for interfaces/abstract types and may intentionally
            // override an otherwise concrete declared class.
            $explicitTarget = $this->explicitNestedHydrationTarget($property);
            if ($explicitTarget !== null) {
                $this->assertValidNestedHydrationTarget($property, $explicitTarget);
                $targets[$property->getName()] = $explicitTarget;
                $explicitTargets[$property->getName()] = $explicitTarget;
                continue;
            }

            if ($property->getTypeConstruct() !== TypeConstruct::ClassType) {
                continue;
            }

            $propertyType = $property->getType();
            // A selected mutator that already produces the declared class owns this conversion completely.
            if ($this->lastMutatorOutputType($property) === $propertyType) {
                continue;
            }

            if (!class_exists($propertyType) && !interface_exists($propertyType)) {
                continue;
            }
            $reflection = new ReflectionClass($propertyType);
            // Internal PHP classes retain their purpose-built mutators rather than being treated as domain objects.
            if (!$reflection->isUserDefined()) {
                continue;
            }
            if (!$reflection->isInstantiable()) {
                throw HydrationException::forMissingNestedHydrationTarget(
                    $this->classDescriptor->getClassName(),
                    $property->getName(),
                    $propertyType,
                );
            }

            $targets[$property->getName()] = $reflection->getName();
        }

        return [$targets, $explicitTargets];
    }

    /**
     * @param array<string, class-string> $targets
     *
     * @return array<class-string, string>
     */
    private function buildNestedHydratorVariables(array $targets): array
    {
        // Multiple properties of the same target class share one child hydrator instance and one captured variable.
        $variables = [];
        foreach ($targets as $target) {
            if (!isset($variables[$target])) {
                $variables[$target] = 'nestedHydrator' . count($variables);
            }
        }

        return $variables;
    }

    /** @return class-string|null */
    private function explicitNestedHydrationTarget(PropertyAnalyzer $property): ?string
    {
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() !== HydrateAs::class) {
                continue;
            }

            $hydrateAs = $attribute->newInstance();
            if ($hydrateAs instanceof HydrateAs) {
                return $hydrateAs->className();
            }
        }

        return null;
    }

    /** @param class-string $target */
    private function assertValidNestedHydrationTarget(PropertyAnalyzer $property, string $target): void
    {
        // Nested hydration needs a class HydraType itself can instantiate and compile; internal conversion remains the
        // responsibility of explicit value mutators such as DateTimeFormat.
        if (!class_exists($target)) {
            throw HydrationException::forInvalidNestedHydrationTarget(
                $this->classDescriptor->getClassName(),
                $property->getName(),
                $target,
            );
        }

        $reflection = new ReflectionClass($target);
        if (!$reflection->isUserDefined() || !$reflection->isInstantiable()) {
            throw HydrationException::forInvalidNestedHydrationTarget(
                $this->classDescriptor->getClassName(),
                $property->getName(),
                $target,
            );
        }

        $propertyType = $property->getType();
        if (
            $propertyType !== Type::Mixed->value
            && $propertyType !== Type::Object->value
            && !is_a($target, $propertyType, true)
        ) {
            throw HydrationException::forIncompatibleNestedHydrationTarget(
                $this->classDescriptor->getClassName(),
                $property->getName(),
                $target,
                $propertyType,
            );
        }
    }

    private function lastMutatorOutputType(PropertyAnalyzer $property): ?string
    {
        // Only the last mutator describes the final value entering inferred conversion; earlier output types are
        // intermediate expressions and cannot suppress automatic nested hydration.
        $outputType = null;
        foreach ($property->getAttributes() as $attribute) {
            if (!is_a($attribute->getName(), MutatorInterface::class, true)) {
                continue;
            }

            $mutator = $attribute->newInstance();
            if ($mutator instanceof MutatorInterface) {
                $outputType = $mutator->outputType();
            }
        }

        return $outputType;
    }

    /** @param class-string $target */
    private function compileNestedHydration(string $inputExpression, string $target): string
    {
        $hydrator = $this->nestedHydratorVariables[$target];
        $targetClass = '\\' . ltrim($target, '\\');

        // Evaluate the input once. Existing domain objects pass through, while array input takes the compiled child path
        // without a factory lookup; the child's array parameter rejects every other shape.
        return "((\$hydraNestedValue = {$inputExpression}) instanceof {$targetClass}"
            . " ? \$hydraNestedValue : \${$hydrator}->hydrate(\$hydraNestedValue))";
    }

    /** @param class-string $target */
    private function compileNestedExtraction(string $inputExpression, string $target, bool $snakeCase): string
    {
        $hydrator = $this->nestedHydratorVariables[$target];
        $namingConvention = $snakeCase ? 'SnakeCase' : 'CamelCase';

        // Propagating the convention keeps every level of an extracted graph consistently camelCase or snake_case.
        return "\${$hydrator}->extract({$inputExpression}, NamingConvention::{$namingConvention})";
    }

    private function appendNestedHydratorResolutions(PhpCodeBuilder $code): void
    {
        // The generated property shares a child across camel/snake readers and writers; the local variable captures it
        // in whichever closure is being created, avoiding both repeated construction and per-object factory calls.
        foreach ($this->nestedHydratorVariables as $target => $variable) {
            $targetClass = '\\' . ltrim($target, '\\');
            $code->line(
                "\${$variable} = \$this->{$variable} ??= "
                . "\$this->hydratorFactory->create({$targetClass}::class);",
            );
        }
    }

    private function nestedHydratorUseClause(): string
    {
        // Returning an empty clause is part of the zero-cost contract: scalar-only closures capture nothing and retain
        // the exact source shape used before nested hydration was introduced.
        if ($this->nestedHydratorVariables === []) {
            return '';
        }

        $variables = array_map(
            static fn (string $variable): string => '$' . $variable,
            array_values($this->nestedHydratorVariables),
        );

        return ' use (' . implode(', ', $variables) . ')';
    }
}
