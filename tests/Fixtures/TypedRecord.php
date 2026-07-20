<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class TypedRecord
{
    public function __construct(
        private int $id,
        private string $displayName,
        private bool $enabled,
        private RecordState $state,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getState(): RecordState
    {
        return $this->state;
    }
}
