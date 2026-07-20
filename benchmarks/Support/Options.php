<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Support;

use InvalidArgumentException;

final class Options
{
    /** @param array<string, mixed> $options */
    public static function integer(array $options, string $name, int $default, int $minimum = 1): int
    {
        $configured = $options[$name] ?? $default;
        $value = is_scalar($configured) ? (int) $configured : $default;

        return max($minimum, $value);
    }

    /**
     * @param array<string, mixed> $options
     * @param non-empty-list<int>  $default
     *
     * @return non-empty-list<int>
     */
    public static function integerList(array $options, string $name, array $default, int $minimum = 1): array
    {
        $configured = $options[$name] ?? null;
        $source = is_string($configured) ? $configured : implode(',', $default);
        $values = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $source)),
            static fn (int $value): bool => $value >= $minimum,
        )));

        if ($values === []) {
            throw new InvalidArgumentException(sprintf(
                '%s must contain integers greater than or equal to %d.',
                $name,
                $minimum,
            ));
        }

        return $values;
    }
}
