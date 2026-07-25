<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

use MakerMill\HydraType\Rules\Optional;

final readonly class PromotedOptionalRecord
{
    public function __construct(
        private string $name,
        #[Optional]
        private int $id = 33,
    ) {
    }

    /** @return array{name: string, id: int} */
    public function values(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
        ];
    }
}
