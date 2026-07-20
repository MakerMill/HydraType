<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

class SimpleUser
{
    public function __construct(
        private int $id,
        private string $userName,
        private string $password,
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
}
