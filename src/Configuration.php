<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

/**
 * Defines the identity, location, and lifecycle policy of generated hydrators.
 *
 * The factory belongs to the configuration so its in-memory hydrator cache cannot leak across cache contexts.
 */
final class Configuration
{
    private const DEFAULT_HYDRATOR_NAMESPACE = 'MakerMill\\HydraType\\Generated';

    private ?HydratorFactory $hydratorFactory = null;
    private readonly ?DefaultCacheDirectory $defaultCacheDirectory;
    private bool $defaultCacheDirectoryTrusted = false;
    private readonly string $hydratorNamespace;
    private readonly string $hydratorDirectory;
    private readonly CacheMode $cacheMode;

    public function __construct(
        string $hydratorNamespace = self::DEFAULT_HYDRATOR_NAMESPACE,
        ?string $hydratorDirectory = null,
        CacheMode $cacheMode = CacheMode::Auto,
    ) {
        $this->hydratorNamespace = $hydratorNamespace;
        if ($hydratorDirectory === null) {
            $this->defaultCacheDirectory = DefaultCacheDirectory::forProject(dirname(__DIR__));
            $this->hydratorDirectory = $this->defaultCacheDirectory->path();
        } else {
            $this->defaultCacheDirectory = null;
            $this->hydratorDirectory = $hydratorDirectory;
        }
        $this->cacheMode = $cacheMode;
    }

    public function getHydratorFactory(): HydratorFactory
    {
        if ($this->hydratorFactory === null) {
            $this->hydratorFactory = new HydratorFactory($this);
        }
        return $this->hydratorFactory;
    }

    public function getHydratorNamespace(): string
    {
        return $this->hydratorNamespace;
    }

    public function getHydratorDirectory(): string
    {
        return $this->hydratorDirectory;
    }

    public function getCacheMode(): CacheMode
    {
        return $this->cacheMode;
    }

    public function getCacheKey(): string
    {
        // A different generated namespace or directory must never resolve to the same generated class identity.
        return hash('sha256', implode(':', [
            $this->hydratorNamespace,
            $this->hydratorDirectory,
        ]));
    }

    /** @internal */
    public function assertHydratorDirectoryIsTrusted(): void
    {
        if ($this->defaultCacheDirectory === null || $this->defaultCacheDirectoryTrusted) {
            return;
        }

        $this->defaultCacheDirectoryTrusted = $this->defaultCacheDirectory->assertTrusted();
    }
}
