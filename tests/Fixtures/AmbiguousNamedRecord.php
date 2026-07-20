<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class AmbiguousNamedRecord
{
    public function __construct(
        private string $displayName,
        private string $display_name,
    ) {
    }

    /** @return array{displayName: string, display_name: string} */
    public function values(): array
    {
        return [
            'displayName' => $this->displayName,
            'display_name' => $this->display_name,
        ];
    }
}
