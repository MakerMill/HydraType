<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use ReflectionClass;
use ReflectionException;

/**
 * Maps one target class and cache configuration to its generated class and file identities.
 *
 * Keeping this mapping in one place ensures compilation, lookup, warm-up, and clearing address the same cache entry.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class ClassDescriptor
{
    /**
     * @param class-string<T>                $className
     * @param \MakerMill\HydraType\Configuration $configuration
     */
    public function __construct(
        private string $className,
        private Configuration $configuration
    ) {
        if (!class_exists($this->className)) {
            throw HydrationException::forInvalidClass($this->className);
        }
    }

    public function getSourceFilePath(): string
    {
        try {
            $reflection = new ReflectionClass($this->className);
            $filePath = $reflection->getFileName();
            if ($filePath === false) {
                throw HydrationException::forInvalidClass($this->className);
            }
            return $filePath;
        } catch (ReflectionException $e) {
            throw HydrationException::forReflectionError($this->className, $e);
        }
    }

    public function getHydratorFilePath(): string
    {
        return $this->configuration->getHydratorDirectory() .
            DIRECTORY_SEPARATOR . $this->getShortHydratorClassName() . '.php';
    }

    /**
     * @return class-string<T>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function getFQClassName(): string
    {
        return "\\" . $this->className;
    }

    public function getHydratorClassName(): string
    {
        return $this->configuration->getHydratorNamespace() . "\\" . $this->getShortHydratorClassName();
    }

    public function getShortClassName(): string
    {
        // Extract the short class name from the fully qualified name
        $parts = explode('\\', $this->className);
        return end($parts);
    }

    public function getShortHydratorClassName(): string
    {
        // Keep generated class names stable for one configuration while preventing collisions across configurations.
        $cacheIdentity = hash('sha256', implode(':', [
            $this->getClassName(),
            (string) HydratorCompiler::CACHE_VERSION,
            $this->configuration->getCacheKey(),
        ]));

        return $this->getShortClassName() . 'Hydrator_' . substr($cacheIdentity, 0, 16);
    }

    public function assertHydratorDirectoryIsTrusted(): void
    {
        $this->configuration->assertHydratorDirectoryIsTrusted();
    }
}
