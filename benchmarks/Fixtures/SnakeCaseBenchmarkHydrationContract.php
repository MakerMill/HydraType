<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

final class SnakeCaseBenchmarkHydrationContract implements BenchmarkHydrationContract
{
    public function renameArrayKeys(array $data): array
    {
        $result = [];
        foreach (array_keys($data) as $key) {
            $parts = explode('_', $key);
            $camelKey = array_shift($parts);
            foreach ($parts as $part) {
                $camelKey .= ucfirst($part);
            }
            $result[$camelKey] = $key;
        }

        return $result;
    }
}
