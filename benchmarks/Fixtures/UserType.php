<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

enum UserType: string
{
    case ADMIN = 'ADMIN';
    case USER = 'USER';
}
