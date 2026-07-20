<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeInterface;
use MakerMill\HydraType\Mutators\DateTimeFormat;

final class DateTimeRecord
{
    public function __construct(
        #[DateTimeFormat('Y-m-d H:i:s')]
        private DateTimeImmutable $createdAt,
        #[DateTimeFormat(DateTimeInterface::ATOM)]
        private ?DateTimeImmutable $publishedAt,
    ) {
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
