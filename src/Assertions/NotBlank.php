<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class NotBlank implements AssertionInterface
{
    public function compileCondition(string $valueExpression): string
    {
        return 'trim(' . $valueExpression . ") !== ''";
    }

    public function message(): string
    {
        return 'Value must not be blank';
    }
}
