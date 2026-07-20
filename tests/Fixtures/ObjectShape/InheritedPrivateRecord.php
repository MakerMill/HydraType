<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class InheritedPrivateRecord extends PrivateParent
{
    private int $id = 0;

    public function id(): int
    {
        return $this->id;
    }
}
