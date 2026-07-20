<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

abstract class ReadonlyParent
{
    public function __construct(protected readonly int $parentId)
    {
    }

    public function parentId(): int
    {
        return $this->parentId;
    }
}
