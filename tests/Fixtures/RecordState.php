<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

enum RecordState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
