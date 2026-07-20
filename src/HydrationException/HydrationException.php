<?php

declare(strict_types=1);

namespace MakerMill\HydraType\HydrationException;

use ReflectionException;
use RuntimeException;
use TypeError;

/** Base exception for failures classified and reported by HydraType itself. */
class HydrationException extends RuntimeException
{
    public static function forCacheFingerprintError(): self
    {
        return new self('Hydration failed: Unable to fingerprint generated-code dependencies.');
    }

    public static function forCacheLockError(string $fileName): self
    {
        return new self("Hydration failed: Unable to acquire cache lock '{$fileName}'.");
    }

    public static function forAmbiguousInputKey(
        string $className,
        string $inputKey,
        string $firstProperty,
        string $secondProperty,
    ): self {
        return new self(
            "Hydration failed: Input key '{$inputKey}' maps to both '{$firstProperty}' and "
            . "'{$secondProperty}' in class '{$className}'.",
        );
    }

    public static function forEmptyData(string $className): self
    {
        return new self("Hydration failed: Empty data for class '{$className}'.");
    }

    public static function forEnumType(string $className, string $propertyName): self
    {
        return new self("Hydration failed: A backed enum is required for property '{$propertyName}' in class '{$className}'.");
    }

    public static function forCacheDirectoryError(string $directory): self
    {
        return new self("Hydration failed: Unable to create cache directory '{$directory}'.");
    }

    public static function forCacheFileDeleteError(string $fileName): self
    {
        return new self("Hydration failed: Unable to delete cache file '{$fileName}'.");
    }

    public static function forCacheFilePublishError(string $fileName): self
    {
        return new self("Hydration failed: Unable to publish cache file '{$fileName}'.");
    }

    public static function forCacheFileWriteError(string $fileName): self
    {
        return new self("Hydration failed: Unable to write cache file '{$fileName}'.");
    }

    public static function forInvalidClass(string $className): self
    {
        return new self("Hydration failed: The class '{$className}' could not be found or loaded.");
    }

    public static function forNonInstantiableClass(string $className): self
    {
        return new self("Hydration failed: The class '{$className}' is not instantiable.");
    }

    public static function forMissingType(string $className, string $propertyName): self
    {
        return new self("Hydration failed: Missing type for property '{$propertyName}' in class '{$className}'.");
    }

    public static function forMissingReadOnlyCache(string $className, string $fileName): self
    {
        return new self(
            "Hydration failed: Cache entry for '{$className}' is missing in read-only mode: {$fileName}. "
            . 'Warm the cache before starting the application or use CacheMode::Auto.',
        );
    }

    public static function forReadOnlyCacheWarmup(): self
    {
        return new self(
            'Hydration failed: Cache warm-up is not available in read-only mode. '
            . 'Use CacheMode::Auto while generating deployment cache files.',
        );
    }

    public static function forReadOnlyCacheClear(): self
    {
        return new self(
            'Hydration failed: Cache clearing is not available in read-only mode. '
            . 'Use CacheMode::Auto while managing deployment cache files.',
        );
    }

    public static function forIntersectionType(string $className, string $propertyName): self
    {
        return new self(
            "Hydration failed: Intersection type not supported for property '{$propertyName}' in class '{$className}'.",
        );
    }

    public static function forInheritedPrivateProperty(
        string $className,
        string $propertyName,
        string $declaringClassName,
    ): self {
        return new self(
            "Hydration failed: Private property '{$propertyName}' inherited by class '{$className}' from "
            . "'{$declaringClassName}' is not supported.",
        );
    }

    public static function forInheritedReadonlyProperty(
        string $className,
        string $propertyName,
        string $declaringClassName,
    ): self {
        return new self(
            "Hydration failed: Readonly property '{$propertyName}' inherited by class '{$className}' from "
            . "'{$declaringClassName}' is not supported on every supported PHP version.",
        );
    }

    public static function forIncompleteCacheWarmup(string $className, string $fileName): self
    {
        return new self(
            "Hydration failed: Warm-up did not produce a current cache entry for '{$className}' at '{$fileName}'.",
        );
    }

    public static function forInvalidNestedHydrationTarget(
        string $className,
        string $propertyName,
        string $targetClass,
    ): self {
        return new self(
            "Hydration failed: Nested hydration target '{$targetClass}' for property '{$propertyName}' in class "
            . "'{$className}' must be a concrete user-defined class.",
        );
    }

    public static function forIncompatibleNestedHydrationTarget(
        string $className,
        string $propertyName,
        string $targetClass,
        string $propertyType,
    ): self {
        return new self(
            "Hydration failed: Nested hydration target '{$targetClass}' for property '{$propertyName}' in class "
            . "'{$className}' is not assignable to '{$propertyType}'.",
        );
    }

    public static function forMissingNestedHydrationTarget(
        string $className,
        string $propertyName,
        string $propertyType,
    ): self {
        return new self(
            "Hydration failed: Property '{$propertyName}' in class '{$className}' declares non-concrete nested type "
            . "'{$propertyType}'. Select a concrete user-defined class with #[HydrateAs(...)] or use a mutator that "
            . 'produces the declared type.',
        );
    }

    public static function forOptionalPropertyWithoutDefault(string $className, string $propertyName): self
    {
        return new self(
            "Hydration failed: Optional property '{$propertyName}' in class '{$className}' requires a default value.",
        );
    }

    public static function forHydrationError(string $className, ReflectionException|TypeError $previous): self
    {
        return new self(
            "Hydration failed: Unable to hydrate class '{$className}': {$previous->getMessage()}",
            previous: $previous,
        );
    }

    public static function forReflectionError(string $className, ReflectionException $previous): self
    {
        return new self(
            "Hydration failed: Unable to inspect class '{$className}': {$previous->getMessage()}",
            previous: $previous,
        );
    }

    public static function forUnionType(string $className, string $propertyName): self
    {
        return new self(
            "Hydration failed: Union type not supported for property '{$propertyName}' in class '{$className}'.",
        );
    }

    public static function forUnsupportedType(string $className, string $propertyName, string $type): self
    {
        return new self(
            "Hydration failed: Property '{$propertyName}' in class '{$className}' has unsupported type '{$type}' "
            . 'and no mutator produces that type.',
        );
    }

    public static function forUnknownReflectionType(string $className, string $propertyName): self
    {
        return new self(
            "Hydration failed: Property '{$propertyName}' in class '{$className}' has an unknown reflection type.",
        );
    }
}
