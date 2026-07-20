<?php

declare(strict_types = 1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use ReflectionEnum;

/**
 * Rejects object shapes that cannot be represented safely by the direct-assignment strategy.
 *
 * Validation happens before source generation so unsupported classes fail without publishing a broken cache entry.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class ClassValidator
{
    /** @param ClassAnalyzer<T> $classAnalyzer */
    public function __construct(private ClassAnalyzer $classAnalyzer)
    {
    }

    public function validate(): void
    {
        $this->validateClass();
        $this->validateProperties();
    }

    private function validateClass(): void
    {
        if (!$this->classAnalyzer->canCreateWithoutConstructor()) {
            throw HydrationException::forNonInstantiableClass($this->classAnalyzer->getClassName());
        }
    }

    public function validateProperties(): void
    {
        foreach ($this->classAnalyzer->getInheritedPrivateProperties() as $property) {
            throw HydrationException::forInheritedPrivateProperty(
                $this->classAnalyzer->getClassName(),
                $property->getName(),
                $property->getDeclaringClassName(),
            );
        }

        $properties = $this->classAnalyzer->getProperties();
        foreach ($properties as $property) {
            $this->validateProperty($property);
        }
    }

    /**
     * @param \MakerMill\HydraType\PropertyAnalyzer $property
     *
     * @return void
     */
    public function validateProperty(PropertyAnalyzer $property): void
    {
        // Constructors are bypassed, so Optional can preserve only an actual property default.
        if ($property->isOptional() && !$property->hasDefaultValue()) {
            throw HydrationException::forOptionalPropertyWithoutDefault(
                $this->classAnalyzer->getClassName(),
                $property->getName(),
            );
        }

        // PHP 8.2 cannot initialize a parent-declared readonly property from the child scope used by the writer.
        if (
            $property->isReadOnly()
            && $property->getDeclaringClassName() !== $this->classAnalyzer->getClassName()
        ) {
            throw HydrationException::forInheritedReadonlyProperty(
                $this->classAnalyzer->getClassName(),
                $property->getName(),
                $property->getDeclaringClassName(),
            );
        }

        // Union and Intersection types are not supported since you can't determine the type of the property based on
        // the data provided cheaply.
        if ($property->getTypeConstruct() === TypeConstruct::EnumType) {
            $enumType = $property->getType();
            if (!enum_exists($enumType)) {
                throw HydrationException::forInvalidClass($enumType);
            }
            $reflection = new ReflectionEnum($enumType);
            $backingType = $reflection->getBackingType();
            if ($backingType === null) {
                throw HydrationException::forEnumType($this->classAnalyzer->getClassName(), $property->getName());
            }
        } elseif ($property->getTypeConstruct() === TypeConstruct::UnionType) {
            throw HydrationException::forUnionType($this->classAnalyzer->getClassName(), $property->getName());
        } elseif ($property->getTypeConstruct() === TypeConstruct::IntersectionType) {
            throw HydrationException::forIntersectionType($this->classAnalyzer->getClassName(), $property->getName());
        }
    }
}
