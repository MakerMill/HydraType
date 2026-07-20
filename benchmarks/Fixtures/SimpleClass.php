<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

class SimpleClass
{
    public function __construct(
        private int $id,
        private string $userName,
        private string $password,
        private UserType $type,
        private bool $active,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
