<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\NestedHydration;

final class NestedUser
{
    public function __construct(
        private int $id,
        private Address $primaryAddress,
        private ?Address $secondaryAddress,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function primaryAddress(): Address
    {
        return $this->primaryAddress;
    }

    public function secondaryAddress(): ?Address
    {
        return $this->secondaryAddress;
    }
}
