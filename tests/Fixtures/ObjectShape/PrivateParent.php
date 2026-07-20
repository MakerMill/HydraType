<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

abstract class PrivateParent
{
    private string $privateParentValue = '';

    public function privateParentValue(): string
    {
        return $this->privateParentValue;
    }
}
