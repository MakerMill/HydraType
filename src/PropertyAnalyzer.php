<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Rules\Optional;
use ReflectionAttribute;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Normalizes reflection metadata for one property into the values needed by validation and code generation.
 *
 * This keeps PHP reflection details out of converters, mutators, and the generated runtime path.
 *
 * @internal
 */
final readonly class PropertyAnalyzer
{
    private string $name;
    private string $type;
    private TypeConstruct $typeConstruct;
    private bool $allowsNull;
    private bool $optional;
    private bool $hasDefaultValue;
    private bool $defaultRequiresAssignment;
    private mixed $defaultValue;
    private bool $readOnly;
    private bool $publiclyWritable;
    private string $declaringClassName;
    private string $camelCaseName;
    private string $snakeCaseName;
    /** @var array<ReflectionAttribute<object>> */
    private array $attributes;

    public function __construct(ReflectionProperty $property)
    {
        // The compiler revisits every property for validation and four generated paths. Snapshot reflection and naming
        // facts once here so those passes consume a stable compile-time model rather than repeating reflection work.
        $reflectionType = $property->getType();
        $this->name = $property->getName();
        $this->declaringClassName = $property->getDeclaringClass()->getName();
        $this->typeConstruct = $this->analyzeTypeConstruct($reflectionType);
        $this->type = $this->analyzeType($reflectionType);
        $this->allowsNull = $reflectionType === null || $reflectionType->allowsNull();
        [
            $this->hasDefaultValue,
            $this->defaultRequiresAssignment,
            $this->defaultValue,
        ] = $this->analyzeDefaultValue($property);
        $this->readOnly = $property->isReadOnly();
        $this->publiclyWritable = $this->analyzePublicWriteAccess($property);
        $this->attributes = $property->getAttributes();
        $this->optional = $this->hasAttribute(Optional::class);
        $this->camelCaseName = self::toCamelCase($this->name);
        $this->snakeCaseName = self::toSnakeCase($this->name);
    }

    private function analyzeTypeConstruct(?ReflectionType $reflectionType): TypeConstruct
    {
        if (!$reflectionType) {
            return TypeConstruct::Undefined;
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            if (enum_exists($reflectionType->getName())) {
                return TypeConstruct::EnumType;
            }
            if (class_exists($reflectionType->getName()) || interface_exists($reflectionType->getName())) {
                return TypeConstruct::ClassType;
            }
            return TypeConstruct::ScalarType;
        }

        if ($reflectionType instanceof ReflectionUnionType) {
            return TypeConstruct::UnionType;
        }

        if ($reflectionType instanceof ReflectionIntersectionType) {
            return TypeConstruct::IntersectionType;
        }

        throw HydrationException::forUnknownReflectionType(
            $this->declaringClassName,
            $this->getName(),
        );
    }

    private function analyzeType(?ReflectionType $reflectionType): string
    {
        if (!$reflectionType) {
            return Type::Mixed->value;
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            if (
                class_exists($reflectionType->getName())
                || interface_exists($reflectionType->getName())
                || enum_exists($reflectionType->getName())
            ) {
                return $reflectionType->getName();
            }
            return match ($reflectionType->getName()) {
                'int' => Type::Int->value,
                'float' => Type::Float->value,
                'string' => Type::String->value,
                'bool' => Type::Bool->value,
                'array' => Type::Array->value,
                'object' => Type::Object->value,
                default => $reflectionType->getName(),
            };
        }

        return Type::Mixed->value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeConstruct(): TypeConstruct
    {
        return $this->typeConstruct;
    }

    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    public function defaultRequiresAssignment(): bool
    {
        return $this->defaultRequiresAssignment;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function isPubliclyWritable(): bool
    {
        return $this->publiclyWritable;
    }

    /** @return class-string */
    public function getDeclaringClassName(): string
    {
        return $this->declaringClassName;
    }

    /**
     * @return array<\ReflectionAttribute<object>>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getCamelCaseName(): string
    {
        return $this->camelCaseName;
    }

    public function getSnakeCaseName(): string
    {
        return $this->snakeCaseName;
    }

    /** @param class-string $attributeClass */
    private function hasAttribute(string $attributeClass): bool
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return true;
            }
        }

        return false;
    }

    private static function toCamelCase(string $value): string
    {
        if (!str_contains($value, '_')) {
            return lcfirst($value);
        }

        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }

    private static function toSnakeCase(string $value): string
    {
        $snakeCase = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($snakeCase ?? $value);
    }

    /** @return array{bool, bool, mixed} */
    private function analyzeDefaultValue(ReflectionProperty $property): array
    {
        if ($property->hasDefaultValue()) {
            // PHP initializes a declared property default even when Reflection bypasses the constructor.
            return [true, false, null];
        }

        if (!$property->isPromoted()) {
            return [false, false, null];
        }

        $constructor = $property->getDeclaringClass()->getConstructor();
        if ($constructor === null) {
            return [false, false, null];
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() !== $property->getName()) {
                continue;
            }

            if (!$parameter->isDefaultValueAvailable()) {
                return [false, false, null];
            }

            // Promoted defaults are constructor assignments. Since hydration deliberately bypasses that constructor,
            // the generated writer must reproduce the assignment when the optional input key is absent.
            return [true, true, $parameter->getDefaultValue()];
        }

        return [false, false, null];
    }

    private function analyzePublicWriteAccess(ReflectionProperty $property): bool
    {
        if (!$property->isPublic() || $property->isReadOnly()) {
            return false;
        }

        // PHP 8.4 added asymmetric property visibility. Keep PHP 8.2 compatibility while ensuring a public getter with
        // protected(set) or private(set) never selects generated assignment from outside the target class scope.
        if (
            !method_exists($property, 'isPrivateSet')
            || !method_exists($property, 'isProtectedSet')
        ) {
            return true;
        }

        return !$property->isPrivateSet() && !$property->isProtectedSet();
    }
}
