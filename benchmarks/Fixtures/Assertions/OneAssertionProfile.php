<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\Assertions;

final class OneAssertionProfile implements AssertionProfile
{
    private int $id;
    #[AtLeast(0)]
    private int $score;
    private int $rank;
    private int $group;
    private int $level;

    public function checksum(): int
    {
        return $this->id + $this->score + $this->rank + $this->group + $this->level;
    }
}
