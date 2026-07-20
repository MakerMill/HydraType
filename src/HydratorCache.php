<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;

/**
 * Provides explicit generated-cache operations for build and deployment workflows.
 *
 * Cache management is kept separate from normal hydration so production preparation does not affect the runtime API.
 */
final readonly class HydratorCache
{
    public function __construct(private Configuration $configuration)
    {
    }

    /**
     * @param class-string ...$classNames
     *
     * @return array<class-string, string>
     */
    public function warm(string ...$classNames): array
    {
        // Warm-up is an explicit cache-writing operation. Allowing it through a read-only configuration would make a
        // deployment intended to trust prebuilt artifacts silently regenerate them instead.
        if ($this->configuration->getCacheMode() === CacheMode::ReadOnly) {
            throw HydrationException::forReadOnlyCacheWarmup();
        }

        // First compile the complete reachable graph. Nested targets are discovered by the same compiler metadata that
        // emits the parent hydrator. A separate scheduled set prevents shared descendants from accumulating in the
        // queue before their first visit, and indexed iteration avoids array_shift's cost as the graph grows.
        $files = [];
        $pendingClassNames = [];
        $scheduledClassNames = [];
        foreach ($classNames as $className) {
            if (isset($scheduledClassNames[$className])) {
                continue;
            }
            $scheduledClassNames[$className] = true;
            $pendingClassNames[] = $className;
        }

        for ($index = 0; isset($pendingClassNames[$index]); $index++) {
            $className = $pendingClassNames[$index];
            $descriptor = new ClassDescriptor($className, $this->configuration);
            $compiler = new HydratorCompiler($descriptor, $this->configuration);
            // A deployment warm-up must replace valid-but-old output as well as missing or invalid cache entries.
            $compiler->compile(true);

            $files[$className] = $descriptor->getHydratorFilePath();
            foreach ($compiler->getNestedClassNames() as $nestedClassName) {
                if (isset($scheduledClassNames[$nestedClassName])) {
                    continue;
                }
                $scheduledClassNames[$nestedClassName] = true;
                $pendingClassNames[] = $nestedClassName;
            }
        }

        // Verify only after the complete graph has been published. Loading generated classes here would make repeated
        // warm-up unreliable because PHP cannot replace an already loaded class with the newly written artifact.
        foreach ($files as $className => $fileName) {
            $descriptor = new ClassDescriptor($className, $this->configuration);
            if ((new HydratorCacheFile($descriptor))->needsCompilation(true)) {
                throw HydrationException::forIncompleteCacheWarmup($className, $fileName);
            }
        }

        return $files;
    }

    /** @param class-string ...$classNames */
    public function clear(string ...$classNames): void
    {
        if ($this->configuration->getCacheMode() === CacheMode::ReadOnly) {
            throw HydrationException::forReadOnlyCacheClear();
        }

        $clearedClasses = [];
        foreach ($classNames as $className) {
            if (isset($clearedClasses[$className])) {
                continue;
            }

            $descriptor = new ClassDescriptor($className, $this->configuration);
            (new HydratorCacheFile($descriptor))->clear();
            $clearedClasses[$className] = true;
        }
    }
}
