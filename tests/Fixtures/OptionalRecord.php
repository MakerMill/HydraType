<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Rules\Optional;

final class OptionalRecord
{
    #[Optional]
    private string $displayLabel = 'Default label';

    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDisplayLabel(): string
    {
        return $this->displayLabel;
    }
}
