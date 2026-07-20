<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\Absolute;
use MakerMill\HydraType\Mutators\Clamp;
use MakerMill\HydraType\Mutators\DelimitedString;
use MakerMill\HydraType\Mutators\RegexReplace;
use MakerMill\HydraType\Mutators\Round;
use MakerMill\HydraType\Mutators\Substring;

final class AdditionalMutatorsRecord
{
    /** @param list<string> $tags */
    public function __construct(
        #[Substring(1, 3)]
        private string $segment,
        #[RegexReplace('/\s+/', '-')]
        #[RegexReplace('/-+/', '-')]
        private string $slug,
        #[DelimitedString('|')]
        private array $tags,
        #[Round(2)]
        private float $price,
        #[Clamp(0, 100)]
        private int $percentage,
        #[Absolute]
        private float $distance,
    ) {
    }

    /**
     * @return array{
     *     segment: string,
     *     slug: string,
     *     tags: list<string>,
     *     price: float,
     *     percentage: int,
     *     distance: float
     * }
     */
    public function values(): array
    {
        return [
            'segment' => $this->segment,
            'slug' => $this->slug,
            'tags' => $this->tags,
            'price' => $this->price,
            'percentage' => $this->percentage,
            'distance' => $this->distance,
        ];
    }
}
