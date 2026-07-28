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
    private readonly HydratorFactory $hydratorFactory;
    /** @var class-string|null */
    private ?string $lastClassName = null;
    /** @var HydratorInterface<object> */
    private HydratorInterface $lastHydrator;

    public function __construct(?Configuration $configuration = null)
    {
        $configuration ??= new Configuration();
        $this->hydratorFactory = $configuration->getHydratorFactory();
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

        $firstObject = $objects[array_key_first($objects)];

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
        // Repeated work for one target class stays on the shortest facade path. Class changes still use the factory's
        // complete in-memory cache, after which that class becomes the next fast-path target.
        if ($className === $this->lastClassName) {
            /** @var HydratorInterface<T> $hydrator */
            $hydrator = $this->lastHydrator;
            return $hydrator;
        }

        $hydrator = $this->hydratorFactory->create($className);
        $this->lastHydrator = $hydrator;
        $this->lastClassName = $className;

        return $hydrator;
    }
}
