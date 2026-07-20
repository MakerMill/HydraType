<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

enum AccessLevel: int
{
    case User = 1;
    case Admin = 2;
}
