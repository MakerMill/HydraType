<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\EmptyStringToNull;
use MakerMill\HydraType\Mutators\RightTrim;
use MakerMill\HydraType\Mutators\Trim;

final class StringNormalizedRecord
{
    public function __construct(
        #[Trim]
        private string $name,
        #[Trim('/')]
        private string $path,
        #[RightTrim]
        private string $suffix,
        #[RightTrim(' .')]
        private string $reference,
        #[Trim]
        #[EmptyStringToNull]
        private ?string $description,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     path: string,
     *     suffix: string,
     *     reference: string,
     *     description: string|null
     * }
     */
    public function values(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'suffix' => $this->suffix,
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }
}
