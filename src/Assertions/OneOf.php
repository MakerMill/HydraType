<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Assertions;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\AssertionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class OneOf implements AssertionInterface
{
    /** @var non-empty-list<bool|float|int|string> */
    private array $values;

    /** @param array<int, bool|float|int|string> $values */
    public function __construct(array $values)
    {
        $this->values = self::validateValues($values);
    }

    public function compileCondition(string $valueExpression): string
    {
        $conditions = [];
        foreach ($this->values as $value) {
            $conditions[] = $valueExpression . ' === ' . PhpLiteral::value($value);
        }

        return implode(' || ', $conditions);
    }

    public function message(): string
    {
        return 'Value must be one of the configured values';
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return non-empty-list<bool|float|int|string>
     */
    private static function validateValues(array $values): array
    {
        if ($values === []) {
            throw new InvalidArgumentException('OneOf values must not be empty.');
        }

        $validated = [];
        foreach ($values as $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                throw new InvalidArgumentException('OneOf values must be strings, integers, floats, or booleans.');
            }
            if (!in_array($value, $validated, true)) {
                $validated[] = $value;
            }
        }

        return $validated;
    }
}
