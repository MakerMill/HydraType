<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class InheritedReadonlyRecord extends ReadonlyParent
{
    private string $name = '';

    public function name(): string
    {
        return $this->name;
    }
}
