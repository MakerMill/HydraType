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
    private readonly string $hydratorNamespace;
    private readonly string $hydratorDirectory;
    private readonly CacheMode $cacheMode;

    public function __construct(
        string $hydratorNamespace = self::DEFAULT_HYDRATOR_NAMESPACE,
        ?string $hydratorDirectory = null,
        CacheMode $cacheMode = CacheMode::Auto,
    ) {
        $this->hydratorNamespace = $hydratorNamespace;
        $this->hydratorDirectory = $hydratorDirectory ?? $this->defaultHydratorDirectory();
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

    private function defaultHydratorDirectory(): string
    {
        // Isolate the default cache per installation so unrelated projects cannot load each other's generated code.
        $projectIdentity = hash('sha256', dirname(__DIR__));

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hydratype' . DIRECTORY_SEPARATOR .
            substr($projectIdentity, 0, 16);
    }
}
