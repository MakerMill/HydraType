<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use Closure;
use MakerMill\HydraType\HydrationException\HydrationException;

/**
 * Owns the filesystem lifecycle of one generated hydrator.
 *
 * Locking, freshness checks, and atomic publication are kept here so every cache writer follows the same concurrency
 * contract.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class HydratorCacheFile
{
    /** @param ClassDescriptor<T> $classDescriptor */
    public function __construct(private ClassDescriptor $classDescriptor)
    {
    }

    public function needsCompilation(bool $refreshFileStatus = false): bool
    {
        $this->classDescriptor->assertHydratorDirectoryIsTrusted();
        $compiledFile = $this->classDescriptor->getHydratorFilePath();
        if ($refreshFileStatus) {
            clearstatcache(true, $compiledFile);
        }

        if (!is_file($compiledFile)) {
            return true;
        }

        return !CacheFingerprint::matches($compiledFile);
    }

    public function clear(): void
    {
        $this->classDescriptor->assertHydratorDirectoryIsTrusted();
        $fileName = $this->classDescriptor->getHydratorFilePath();
        $lockFile = $fileName . '.lock';
        if (!is_file($fileName) && !is_file($lockFile)) {
            return;
        }

        // Clearing uses the same lock as compilation so it cannot remove a file while another process publishes it.
        $lock = fopen($lockFile, 'c');
        if ($lock === false) {
            throw HydrationException::forCacheLockError($lockFile);
        }

        $locked = false;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw HydrationException::forCacheLockError($lockFile);
            }
            $locked = true;

            clearstatcache(true, $fileName);
            if (!is_file($fileName)) {
                return;
            }

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($fileName, true);
            }

            set_error_handler(static fn (): bool => true);
            try {
                $deleted = unlink($fileName);
            } finally {
                restore_error_handler();
            }

            clearstatcache(true, $fileName);
            if (!$deleted && is_file($fileName)) {
                throw HydrationException::forCacheFileDeleteError($fileName);
            }
        } finally {
            if ($locked) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
    }

    /** @param Closure(): string $generateCode */
    public function compile(Closure $generateCode, bool $force = false): void
    {
        $fileName = $this->classDescriptor->getHydratorFilePath();
        $this->ensureDirectory(dirname($fileName));

        $lockFile = $fileName . '.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false) {
            throw HydrationException::forCacheLockError($lockFile);
        }

        $locked = false;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw HydrationException::forCacheLockError($lockFile);
            }
            $locked = true;

            // Another process may have completed compilation while this process waited for the lock.
            if (!$force && !$this->needsCompilation(true)) {
                return;
            }

            $this->writeAtomically($fileName, $generateCode());
        } finally {
            if ($locked) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        $this->classDescriptor->assertHydratorDirectoryIsTrusted();
        if (is_dir($directory)) {
            return;
        }

        if (file_exists($directory)) {
            if (is_dir($directory)) {
                return;
            }
            throw HydrationException::forCacheDirectoryError($directory);
        }

        set_error_handler(static fn (): bool => true);
        try {
            $created = mkdir($directory, 0700, true);
        } finally {
            restore_error_handler();
        }

        if (!$created && !is_dir($directory)) {
            throw HydrationException::forCacheDirectoryError($directory);
        }

        $this->classDescriptor->assertHydratorDirectoryIsTrusted();
    }

    private function writeAtomically(string $fileName, string $code): void
    {
        // Readers see either the previous complete file or the new complete file, never a partially written hydrator.
        $temporaryFile = $fileName . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporaryFile, 'x+b');
        if ($stream === false) {
            throw HydrationException::forCacheFileWriteError($temporaryFile);
        }

        try {
            $length = strlen($code);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($stream, substr($code, $offset));
                if ($written === false || $written === 0) {
                    throw HydrationException::forCacheFileWriteError($temporaryFile);
                }
                $offset += $written;
            }

            if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw HydrationException::forCacheFileWriteError($temporaryFile);
            }

            if (!fclose($stream)) {
                throw HydrationException::forCacheFileWriteError($temporaryFile);
            }
            $stream = null;

            // Rename is the publication point after the temporary file has been fully flushed to disk.
            if (!rename($temporaryFile, $fileName)) {
                throw HydrationException::forCacheFilePublishError($fileName);
            }

            clearstatcache(true, $fileName);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($fileName, true);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}
