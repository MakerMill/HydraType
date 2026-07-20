<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Interfaces;

use MakerMill\HydraType\HydratorFactory;

/**
 * Marks generated hydrators that need the owning factory to resolve nested hydrators.
 *
 * Scalar-only generated classes do not implement this contract and retain their zero-argument construction path.
 *
 * @internal
 *
 * @template T of object
 * @extends HydratorInterface<T>
 */
interface FactoryAwareHydratorInterface extends HydratorInterface
{
    public function __construct(HydratorFactory $hydratorFactory);
}
