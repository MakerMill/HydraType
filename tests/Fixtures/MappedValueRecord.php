<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\MapValue;

final class MappedValueRecord
{
    public function __construct(
        #[MapValue(['enabled' => true, 'disabled' => false])]
        private bool $enabled,
        #[MapValue(['administrator' => 2, 'regular' => 1])]
        private AccessLevel $accessLevel,
        #[MapValue([1 => 'first', 2 => 'second'])]
        private string $numericLabel,
        #[MapValue(['legacy' => 42])]
        private int $identifier,
    ) {
    }

    /**
     * @return array{
     *     enabled: bool,
     *     accessLevel: AccessLevel,
     *     numericLabel: string,
     *     identifier: int
     * }
     */
    public function values(): array
    {
        return [
            'enabled' => $this->enabled,
            'accessLevel' => $this->accessLevel,
            'numericLabel' => $this->numericLabel,
            'identifier' => $this->identifier,
        ];
    }
}
