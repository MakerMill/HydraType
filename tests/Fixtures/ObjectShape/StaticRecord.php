<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class StaticRecord
{
    public static string $metadata = 'unchanged';
    private int $id = 0;

    public function id(): int
    {
        return $this->id;
    }
}
