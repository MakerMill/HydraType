<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;

/** Selects the concrete class used to hydrate a nested object property. */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HydrateAs
{
    /** @param class-string $className */
    public function __construct(private string $className)
    {
    }

    /** @return class-string */
    public function className(): string
    {
        return $this->className;
    }
}
