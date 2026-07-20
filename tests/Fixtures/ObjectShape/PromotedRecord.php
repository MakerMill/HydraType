<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class PromotedRecord
{
    private static int $constructorCalls = 0;

    public function __construct(
        private int $id,
        protected string $name,
    ) {
        self::$constructorCalls++;
    }

    public static function constructorCalls(): int
    {
        return self::$constructorCalls;
    }

    /** @return array{id: int, name: string} */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
