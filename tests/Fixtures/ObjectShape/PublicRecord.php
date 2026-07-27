<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class PublicRecord
{
    public int $id = 0;
    public string $displayName = '';
    public bool $active = false;

    /** @return array{id: int, displayName: string, active: bool} */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'active' => $this->active,
        ];
    }
}
