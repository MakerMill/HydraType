<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class PlainRecord
{
    private int $id = 0;
    private string $displayName = '';

    /** @return array{id: int, displayName: string} */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
        ];
    }
}
