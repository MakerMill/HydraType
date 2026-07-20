<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\Assertions;

final class UnassertedProfile implements AssertionProfile
{
    private int $id;
    private int $score;
    private int $rank;
    private int $group;
    private int $level;

    public function checksum(): int
    {
        return $this->id + $this->score + $this->rank + $this->group + $this->level;
    }
}
