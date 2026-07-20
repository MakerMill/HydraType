<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures\Assertions;

use Attribute;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class AtLeast implements AssertionInterface
{
    public function __construct(private int $minimum)
    {
    }

    public function compileCondition(string $valueExpression): string
    {
        return "{$valueExpression} >= {$this->minimum}";
    }

    public function message(): string
    {
        return "Value must be at least {$this->minimum}.";
    }
}
