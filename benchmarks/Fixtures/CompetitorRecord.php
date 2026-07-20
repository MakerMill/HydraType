<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

final class CompetitorRecord
{
    public function __construct(
        private int $id = 0,
        private string $userName = '',
        private string $email = '',
        private string $city = '',
        private bool $active = false,
    ) {
    }

    public function checksum(): int
    {
        return $this->id
            + strlen($this->userName)
            + strlen($this->email)
            + strlen($this->city)
            + (int) $this->active;
    }
}
