<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

interface ObjectCreationTarget
{
    public function checksum(): int;
}
