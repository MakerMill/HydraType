<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class InheritedAccessibleRecord extends AccessibleParent
{
    private bool $active = false;

    /** @return array{publicParentId: int, protectedParentName: string, active: bool} */
    public function values(): array
    {
        return [
            'publicParentId' => $this->publicParentId,
            'protectedParentName' => $this->protectedParentName,
            'active' => $this->active,
        ];
    }
}
