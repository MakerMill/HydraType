<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\Interfaces\HydratorInterface;

/**
 * Public facade for hydration and extraction.
 *
 * It hides generated-code resolution and reuse so consumers work with target classes rather than hydrator instances.
 */
final class HydraType
{
    private readonly Configuration $configuration;

    public function __construct(?Configuration $configuration = null)
    {
        $this->configuration = $configuration ?? new Configuration();
    }

    /**
     * @template T of object
     *
     * @param class-string<T>     $className
     * @param array<string, mixed> $data
     *
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        return $this->hydrator($className)->hydrate($data);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                   $className
     * @param array<int, array<string, mixed>> $dataSet
     *
     * @return array<int, T>
     */
    public function hydrateMany(string $className, array $dataSet): array
    {
        return $this->hydrator($className)->hydrateMany($dataSet);
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(
        object $object,
        NamingConvention $namingConvention = NamingConvention::CamelCase,
    ): array {
        return $this->hydrator($object::class)->extract($object, $namingConvention);
    }

    /**
     * @param array<int, object> $objects
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractMany(
        array $objects,
        NamingConvention $namingConvention = NamingConvention::CamelCase,
    ): array {
        if ($objects === []) {
            return [];
        }

        $firstObject = reset($objects);

        return $this->hydrator($firstObject::class)->extractMany($objects, $namingConvention);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return HydratorInterface<T>
     */
    public function hydrator(string $className): HydratorInterface
    {
        return $this->configuration->getHydratorFactory()->create($className);
    }
}
