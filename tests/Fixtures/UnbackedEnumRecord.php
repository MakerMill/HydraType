<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class UnbackedEnumRecord
{
    private UnbackedState $state;

    public function __construct(UnbackedState $state)
    {
        $this->state = $state;
    }

    public function state(): UnbackedState
    {
        return $this->state;
    }
}
