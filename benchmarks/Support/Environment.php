<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Support;

final class Environment
{
    public static function summary(): string
    {
        return sprintf(
            'PHP %s | JIT: %s | Xdebug: %s',
            PHP_VERSION,
            ini_get('opcache.jit') ?: 'off',
            self::xdebugStatus(),
        );
    }

    private static function xdebugStatus(): string
    {
        if (!extension_loaded('xdebug')) {
            return 'off';
        }

        $modes = function_exists('xdebug_info') ? xdebug_info('mode') : [];
        if (!is_array($modes) || $modes === []) {
            return 'loaded, mode off';
        }

        $normalizedModes = [];
        foreach ($modes as $mode) {
            if (is_string($mode)) {
                $normalizedModes[] = $mode;
            }
        }

        return $normalizedModes === [] ? 'loaded, mode off' : implode(',', $normalizedModes);
    }
}
