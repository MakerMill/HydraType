<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Interfaces\AssertionInterface;
use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Mutators\HydrateAs;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Builds the compile-time model of a target class and its properties.
 *
 * Reflection is contained here so generated hydrators never inspect class metadata at runtime.
 *
 * @internal
 *
 * @template T of object
 */
final class ClassAnalyzer
{
    /**
     * @var ReflectionClass<object>
     */
    private readonly ReflectionClass $reflectionClass;
    /** @var array<int, PropertyAnalyzer> */
    private array $properties = [];
    /** @var array<int, PropertyAnalyzer> */
    private array $inheritedPrivateProperties = [];

    /**
     * @param ClassDescriptor<T> $classDescriptor
     */
    public function __construct(private readonly ClassDescriptor $classDescriptor)
    {
        try {
            $this->reflectionClass = new ReflectionClass($this->classDescriptor->getClassName());
        } catch (ReflectionException $e) {
            throw HydrationException::forReflectionError($this->classDescriptor->getClassName(), $e);
        }

        $this->analyzeProperties();
    }

    private function analyzeProperties(): void
    {
        foreach ($this->reflectionClass->getProperties() as $property) {
            // Static state belongs to the class, not to an individual hydrated object.
            if ($property->isStatic()) {
                continue;
            }
            $this->properties[] = new PropertyAnalyzer($property);
        }

        // Reflection omits parent-private properties from a child. Find them explicitly so compilation fails instead
        // of silently producing an object with missing state that a child-scoped closure cannot access.
        $parent = $this->reflectionClass->getParentClass();
        while ($parent !== false) {
            foreach ($parent->getProperties(ReflectionProperty::IS_PRIVATE) as $property) {
                if (
                    !$property->isStatic()
                    && $property->getDeclaringClass()->getName() === $parent->getName()
                ) {
                    $this->inheritedPrivateProperties[] = new PropertyAnalyzer($property);
                }
            }
            $parent = $parent->getParentClass();
        }
    }

    /**
     * @return class-string<T>
     */
    public function getClassName(): string
    {
        return $this->classDescriptor->getClassName();
    }

    /**
     * @return array<int, PropertyAnalyzer>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array<int, PropertyAnalyzer>
     */
    public function getInheritedPrivateProperties(): array
    {
        return $this->inheritedPrivateProperties;
    }

    public function allowsEmptyData(): bool
    {
        foreach ($this->properties as $property) {
            if (!$property->allowsNull() && !$property->isOptional()) {
                return false;
            }
        }

        return true;
    }

    public function hasOnlyPubliclyWritableProperties(): bool
    {
        foreach ($this->properties as $property) {
            if (!$property->isPubliclyWritable()) {
                return false;
            }
        }

        return true;
    }

    public function hasConstructor(): bool
    {
        return $this->reflectionClass->getConstructor() !== null;
    }

    public function canCreateWithoutConstructor(): bool
    {
        return !$this->reflectionClass->isAbstract()
            && !$this->reflectionClass->isInterface()
            && !$this->reflectionClass->isTrait()
            && !$this->reflectionClass->isEnum();
    }

    /** @return list<class-string> */
    public function getCacheDependencies(): array
    {
        // This is deliberately a compile-time dependency graph, not every class the hydrated object may use. A source
        // file belongs here when changing it can alter generated expressions or a decision made while compiling them.
        // Changes to the compiler itself are represented separately by HydratorCompiler::CACHE_VERSION.
        $dependencies = [];

        // The target, its parents, and its traits define the properties and construction strategy being compiled.
        $this->collectClassDependencies($this->reflectionClass, $dependencies);

        foreach ($this->properties as $property) {
            $propertyType = $property->getType();
            // Enum definitions determine backing conversion. Class definitions determine whether automatic nested
            // hydration is valid and which concrete class the generated assignment delegates to.
            if ($property->getTypeConstruct() === TypeConstruct::EnumType && enum_exists($propertyType)) {
                $this->collectClassDependencies(new ReflectionClass($propertyType), $dependencies);
            } elseif ($property->getTypeConstruct() === TypeConstruct::ClassType && class_exists($propertyType)) {
                $this->collectClassDependencies(new ReflectionClass($propertyType), $dependencies);
            }

            foreach ($property->getAttributes() as $attribute) {
                $attributeClass = $attribute->getName();
                if ($attributeClass === HydrateAs::class) {
                    // HydrateAs makes its configured class part of the generated dependency graph even when the
                    // declared property type is an interface or abstract class that cannot identify that target.
                    $this->collectClassDependencies(new ReflectionClass(HydrateAs::class), $dependencies);
                    $hydrateAs = $attribute->newInstance();
                    if ($hydrateAs instanceof HydrateAs && class_exists($hydrateAs->className())) {
                        $this->collectClassDependencies(
                            new ReflectionClass($hydrateAs->className()),
                            $dependencies,
                        );
                    }
                    continue;
                }

                // Mutator and assertion source is executable compiler input: their methods emit expressions embedded
                // in the cached hydrator, so changing that source must invalidate the generated file.
                if (
                    !is_a($attributeClass, MutatorInterface::class, true)
                    && !is_a($attributeClass, ExtractionMutatorInterface::class, true)
                    && !is_a($attributeClass, AssertionInterface::class, true)
                ) {
                    continue;
                }
                if (!class_exists($attributeClass)) {
                    continue;
                }

                $reflection = new ReflectionClass($attributeClass);
                $this->collectClassDependencies($reflection, $dependencies);
            }
        }

        // Reflection order must not make an otherwise identical dependency set produce a different fingerprint.
        $classNames = array_keys($dependencies);
        sort($classNames, SORT_STRING);

        return $classNames;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<class-string, true> $dependencies
     */
    private function collectClassDependencies(ReflectionClass $reflection, array &$dependencies): void
    {
        $className = $reflection->getName();
        // Deduplicate recursive parent/trait traversal. Internal PHP classes have no application source file to hash.
        if (isset($dependencies[$className]) || $reflection->getFileName() === false) {
            return;
        }
        $dependencies[$className] = true;

        foreach ($reflection->getTraits() as $trait) {
            $this->collectClassDependencies($trait, $dependencies);
        }

        $parent = $reflection->getParentClass();
        if ($parent !== false) {
            $this->collectClassDependencies($parent, $dependencies);
        }
    }
}
