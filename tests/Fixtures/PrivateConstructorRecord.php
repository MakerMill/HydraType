<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class PrivateConstructorRecord
{
    private function __construct(private int $id)
    {
    }

    public function id(): int
    {
        return $this->id;
    }
}
