<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\ColdCompilation;

use DateTimeImmutable;
use MakerMill\HydraType\Assertions\NotEmpty;
use MakerMill\HydraType\Mutators\DateTimeFormat;
use MakerMill\HydraType\Mutators\JsonValue;
use MakerMill\HydraType\Mutators\MapValue;
use MakerMill\HydraType\Mutators\Trim;

final class ColdCompilationTarget
{
    public function __construct(
        private int $id,
        #[Trim]
        #[NotEmpty]
        private string $displayName,
        #[MapValue(['yes' => true, 'no' => false])]
        private bool $active,
        #[JsonValue]
        private array $settings,
        #[DateTimeFormat('Y-m-d')]
        private DateTimeImmutable $createdAt,
        private ColdCompilationChild $child,
        private float $score,
        private string $email,
        private string $city,
        private int $revision,
    ) {
    }
}
