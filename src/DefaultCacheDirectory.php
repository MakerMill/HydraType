<?php

declare(strict_types=1);

namespace MakerMill\HydraType;

use MakerMill\HydraType\HydrationException\HydrationException;

/**
 * Derives and validates the zero-configuration cache location.
 *
 * Generated hydrators are executable PHP. The default directory therefore has to remain controlled by the current
 * operating-system user even when the system temporary directory is shared with other users.
 *
 * @internal
 */
final readonly class DefaultCacheDirectory
{
    private string $temporaryDirectory;
    private ?int $effectiveUserId;
    private string $path;

    public function __construct(
        string $temporaryDirectory,
        string $projectDirectory,
        ?int $effectiveUserId = null,
    ) {
        $resolvedTemporaryDirectory = realpath($temporaryDirectory);
        if ($resolvedTemporaryDirectory === false || !is_dir($resolvedTemporaryDirectory)) {
            throw HydrationException::forCacheDirectoryError($temporaryDirectory);
        }

        $this->temporaryDirectory = $resolvedTemporaryDirectory;
        $this->effectiveUserId = PHP_OS_FAMILY === 'Windows'
            ? null
            : ($effectiveUserId ?? self::resolveEffectiveUserId());

        $userIdentity = $this->effectiveUserId === null
            ? 'windows'
            : (string) $this->effectiveUserId;
        $projectIdentity = substr(hash('sha256', $projectDirectory), 0, 16);
        $this->path = $this->temporaryDirectory . DIRECTORY_SEPARATOR
            . "hydratype-{$userIdentity}-{$projectIdentity}";
    }

    public static function forProject(string $projectDirectory): self
    {
        return new self(sys_get_temp_dir(), $projectDirectory);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return bool Whether the cache directory already exists and has been validated.
     */
    public function assertTrusted(): bool
    {
        $this->assertTemporaryDirectoryIsTrusted();
        clearstatcache(true, $this->path);

        if (!file_exists($this->path) && !is_link($this->path)) {
            return false;
        }
        if (is_link($this->path) || !is_dir($this->path)) {
            throw HydrationException::forUntrustedCacheDirectory(
                $this->path,
                'the path must be a real directory',
            );
        }

        if ($this->effectiveUserId === null) {
            return true;
        }

        $owner = fileowner($this->path);
        if ($owner === false || $owner !== $this->effectiveUserId) {
            throw HydrationException::forUntrustedCacheDirectory(
                $this->path,
                'the directory is not owned by the current operating-system user',
            );
        }

        $permissions = fileperms($this->path);
        if ($permissions === false || ($permissions & 0077) !== 0) {
            throw HydrationException::forUntrustedCacheDirectory(
                $this->path,
                'the directory must be accessible only by its owner',
            );
        }

        return true;
    }

    private function assertTemporaryDirectoryIsTrusted(): void
    {
        if ($this->effectiveUserId === null) {
            return;
        }

        $permissions = fileperms($this->temporaryDirectory);
        if ($permissions === false) {
            throw HydrationException::forUntrustedCacheDirectory(
                $this->temporaryDirectory,
                'the system temporary directory permissions could not be inspected',
            );
        }

        $writableByOtherUsers = ($permissions & 0022) !== 0;
        $hasStickyBit = ($permissions & 01000) !== 0;
        if ($writableByOtherUsers && !$hasStickyBit) {
            throw HydrationException::forUntrustedCacheDirectory(
                $this->temporaryDirectory,
                'a shared system temporary directory must have its sticky bit set',
            );
        }
    }

    private static function resolveEffectiveUserId(): int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        // fstat() on a securely created temporary file exposes the process owner without requiring ext-posix.
        $stream = tmpfile();
        if ($stream !== false) {
            try {
                $status = fstat($stream);
                if ($status !== false) {
                    return $status['uid'];
                }
            } finally {
                fclose($stream);
            }
        }

        throw HydrationException::forCacheUserIdentityError();
    }
}
