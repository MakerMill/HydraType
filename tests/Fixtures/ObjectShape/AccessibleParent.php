<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

abstract class AccessibleParent
{
    public int $publicParentId = 0;
    protected string $protectedParentName = '';
}
