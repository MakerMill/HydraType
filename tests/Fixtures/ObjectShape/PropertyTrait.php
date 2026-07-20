<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

trait PropertyTrait
{
    private string $traitValue = '';

    public function traitValue(): string
    {
        return $this->traitValue;
    }
}
