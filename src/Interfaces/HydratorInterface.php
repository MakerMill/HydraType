<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Interfaces;

use MakerMill\HydraType\NamingConvention;

/**
 * Runtime contract implemented by every generated hydrator.
 *
 * The facade and factory depend on this stable boundary rather than on generated class names.
 *
 * @template T of object
 */
interface HydratorInterface
{
    /**
     * @param  array<string, mixed> $data
     * @return T
     */
    public function hydrate(array $data): object;

    /**
     * @param array<int, array<string, mixed>> $dataSet
     *
     * @return array<int, T>
     */
    public function hydrateMany(array $dataSet): array;

    /**
     * @param T $object
     *
     * @return array<string, mixed>
     */
    public function extract(
        object $object,
        NamingConvention $namingConvention = NamingConvention::CamelCase,
    ): array;

    /**
     * @param array<int, T> $objects
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractMany(
        array $objects,
        NamingConvention $namingConvention = NamingConvention::CamelCase,
    ): array;
}
