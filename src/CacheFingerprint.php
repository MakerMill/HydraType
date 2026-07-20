<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;
use ReflectionClass;

/**
 * Stores and verifies a compact content signature for generated-code dependencies.
 *
 * Auto mode can therefore detect stale hydrators without compiling new source merely to compare it with the cache.
 *
 * @internal
 */
final class CacheFingerprint
{
    private const HEADER_PREFIX = '// hydratype-cache:v1:';

    /**
     * @param list<class-string> $dependencies
     */
    public static function header(array $dependencies): string
    {
        $fingerprint = self::calculate($dependencies);
        if ($fingerprint === null) {
            throw HydrationException::forCacheFingerprintError();
        }

        return self::HEADER_PREFIX . $fingerprint . ':' . base64_encode(implode("\n", $dependencies));
    }

    public static function matches(string $generatedFile): bool
    {
        $stream = fopen($generatedFile, 'rb');
        if ($stream === false) {
            return false;
        }

        // The cache contract lives on the second line, keeping validation bounded regardless of hydrator size.
        try {
            $openTag = fgets($stream);
            $header = fgets($stream);
        } finally {
            fclose($stream);
        }

        if ($openTag === false || trim($openTag) !== '<?php' || $header === false) {
            return false;
        }

        $parts = explode(':', trim($header), 4);
        if (
            count($parts) !== 4
            || $parts[0] !== '// hydratype-cache'
            || $parts[1] !== 'v1'
            || preg_match('/^[0-9a-f]{32}$/', $parts[2]) !== 1
        ) {
            return false;
        }

        $dependencies = self::decodeDependencies($parts[3]);
        if ($dependencies === null) {
            return false;
        }

        $currentFingerprint = self::calculate($dependencies);

        return $currentFingerprint !== null && hash_equals($parts[2], $currentFingerprint);
    }

    /** @return list<class-string>|null */
    private static function decodeDependencies(string $encoded): ?array
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $dependencies = [];
        foreach (explode("\n", $decoded) as $dependency) {
            if ($dependency === '' || (!class_exists($dependency) && !trait_exists($dependency))) {
                return null;
            }
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * @param list<class-string> $dependencies
     */
    private static function calculate(array $dependencies): ?string
    {
        // Content hashing avoids both timestamp false positives and missed edits that preserve a timestamp.
        sort($dependencies, SORT_STRING);
        $context = hash_init('xxh128');
        hash_update($context, (string) HydratorCompiler::CACHE_VERSION . "\0");
        $fingerprintedFiles = [];

        foreach ($dependencies as $dependency) {
            $reflection = new ReflectionClass($dependency);
            $fileName = $reflection->getFileName();
            if ($fileName === false) {
                return null;
            }

            // Class names matter even when multiple symbols share one source file.
            hash_update($context, $dependency . "\0");
            if (!isset($fingerprintedFiles[$fileName])) {
                // Traits and classes commonly share files; hash each file body only once.
                if (!hash_update_file($context, $fileName)) {
                    return null;
                }
                $fingerprintedFiles[$fileName] = true;
            }
            hash_update($context, "\0");
        }

        return hash_final($context);
    }
}
