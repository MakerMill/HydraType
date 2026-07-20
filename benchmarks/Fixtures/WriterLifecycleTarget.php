<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

final class WriterLifecycleTarget
{
    private int $id;
    private string $userName;
    private string $password;
    private string $type;
    private bool $active;

    public function __construct()
    {
        $this->id = 0;
        $this->userName = '';
        $this->password = '';
        $this->type = '';
        $this->active = false;
    }

    public function checksum(): int
    {
        return $this->id
            + strlen($this->userName)
            + strlen($this->password)
            + strlen($this->type)
            + (int) $this->active;
    }
}
