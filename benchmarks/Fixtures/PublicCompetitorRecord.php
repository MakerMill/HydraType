<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

final class PublicCompetitorRecord implements CompetitorRecordInterface
{
    public int $id = 0;
    public string $userName = '';
    public string $email = '';
    public string $city = '';
    public bool $active = false;

    public function checksum(): int
    {
        return $this->id
            + strlen($this->userName)
            + strlen($this->email)
            + strlen($this->city)
            + (int) $this->active;
    }
}
