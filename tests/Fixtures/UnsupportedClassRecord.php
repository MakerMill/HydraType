<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use DateTimeImmutable;

final class UnsupportedClassRecord
{
    public function __construct(private DateTimeImmutable $createdAt)
    {
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
