<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use MakerMill\HydraType\Interfaces\FactoryAwareHydratorInterface;
use MakerMill\HydraType\Interfaces\HydratorInterface;

/**
 * Resolves the compiled hydrator for a target class.
 *
 * The first resolution handles the configured disk-cache lifecycle; later resolutions reuse the in-memory instance.
 */
final class HydratorFactory
{
    /**
     * Class-string keys retain the runtime relationship that PHPDoc cannot express for a heterogeneous array.
     * create() restores the matching generic type at the cache boundary.
     *
     * @var array<class-string, object>
     */
    private array $cache = [];

    public function __construct(private readonly Configuration $configuration)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return HydratorInterface<T>
     */
    public function create(string $className): HydratorInterface
    {
        // Only the first resolution pays for filesystem and cache work; steady-state lookup stays in memory.
        if (isset($this->cache[$className])) {
            /** @var HydratorInterface<T> $cachedHydrator */
            $cachedHydrator = $this->cache[$className];
            return $cachedHydrator;
        }

        $classDescriptor = new ClassDescriptor($className, $this->configuration);

        // ReadOnly deliberately skips fingerprint checks and all writes for the production fast path.
        if ($this->configuration->getCacheMode() === CacheMode::Auto) {
            $cacheFile = new HydratorCacheFile($classDescriptor);
            if ($cacheFile->needsCompilation()) {
                $this->compileHydrator($classDescriptor);
            }
        }
        $this->includeHydratorFile($classDescriptor);

        /** @var class-string<HydratorInterface<T>> $hydrator */
        $hydrator = $classDescriptor->getHydratorClassName();
        $instance = is_a($hydrator, FactoryAwareHydratorInterface::class, true)
            ? new $hydrator($this)
            : new $hydrator();

        $this->cache[$className] = $instance;

        return $instance;
    }

    /** @param ClassDescriptor<object> $classDescriptor */
    private function includeHydratorFile(ClassDescriptor $classDescriptor): void
    {
        $filePath = $classDescriptor->getHydratorFilePath();

        if (is_file($filePath)) {
            include_once $filePath;
        } elseif ($this->configuration->getCacheMode() === CacheMode::ReadOnly) {
            throw HydrationException::forMissingReadOnlyCache(
                $classDescriptor->getClassName(),
                $filePath,
            );
        } else {
            throw HydrationException::forInvalidClass($classDescriptor->getHydratorClassName());
        }
    }

    /** @param ClassDescriptor<object> $classDescriptor */
    private function compileHydrator(ClassDescriptor $classDescriptor): void
    {
        $hydratorCompiler = new HydratorCompiler($classDescriptor, $this->configuration);
        $hydratorCompiler->compile();
    }
}
