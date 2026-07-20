<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\StringReplace;
use MakerMill\HydraType\Mutators\Trim;

final class StringReplacedRecord
{
    public function __construct(
        #[Trim]
        #[StringReplace(' ', '-')]
        #[StringReplace('--', '-')]
        private string $slug,
        #[StringReplace('\\', '/')]
        private string $path,
        #[StringReplace('old', 'new')]
        private ?string $alias,
    ) {
    }

    /** @return array{slug: string, path: string, alias: string|null} */
    public function values(): array
    {
        return [
            'slug' => $this->slug,
            'path' => $this->path,
            'alias' => $this->alias,
        ];
    }
}
