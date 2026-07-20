<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class SnakeNamedRecord
{
    public function __construct(private string $display_name)
    {
    }

    public function getDisplayName(): string
    {
        return $this->display_name;
    }
}
