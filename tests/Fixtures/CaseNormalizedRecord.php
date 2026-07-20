<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\Lowercase;
use MakerMill\HydraType\Mutators\Trim;
use MakerMill\HydraType\Mutators\Uppercase;

final class CaseNormalizedRecord
{
    public function __construct(
        #[Trim]
        #[Lowercase]
        private string $email,
        #[Uppercase]
        private string $countryCode,
        #[Lowercase]
        private ?string $alias,
    ) {
    }

    /** @return array{email: string, countryCode: string, alias: string|null} */
    public function values(): array
    {
        return [
            'email' => $this->email,
            'countryCode' => $this->countryCode,
            'alias' => $this->alias,
        ];
    }
}
