<?php

declare(strict_types=1);

use MakerMill\HydraType\DefaultCacheDirectory;
use MakerMill\HydraType\HydrationException\HydrationException;

it('derives one cache directory per operating-system user and project', function () {
    $temporaryDirectory = testHydratorDirectory() . '/default-cache-root-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        throw new RuntimeException('Unable to create the default-cache test directory.');
    }

    $first = new DefaultCacheDirectory($temporaryDirectory, '/application/one', 1234);
    $same = new DefaultCacheDirectory($temporaryDirectory, '/application/one', 1234);
    $otherUser = new DefaultCacheDirectory($temporaryDirectory, '/application/one', 5678);
    $otherProject = new DefaultCacheDirectory($temporaryDirectory, '/application/two', 1234);

    expect($first->path())->toBe($same->path())
        ->and(dirname($first->path()))->toBe(realpath($temporaryDirectory))
        ->and(basename($first->path()))->toStartWith('hydratype-1234-')
        ->and($first->path())->not->toBe($otherUser->path())
        ->and($first->path())->not->toBe($otherProject->path())
        ->and($first->assertTrusted())->toBeFalse();
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix ownership semantics are not available on Windows.');

it('accepts a private default cache directory owned by the current user', function () {
    $temporaryDirectory = testHydratorDirectory() . '/trusted-cache-root-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        throw new RuntimeException('Unable to create the trusted-cache test directory.');
    }
    $owner = fileowner($temporaryDirectory);
    if ($owner === false) {
        throw new RuntimeException('Unable to inspect the trusted-cache test directory.');
    }

    $cacheDirectory = new DefaultCacheDirectory($temporaryDirectory, '/application', $owner);
    if (!mkdir($cacheDirectory->path(), 0700)) {
        throw new RuntimeException('Unable to create the trusted default cache directory.');
    }

    expect($cacheDirectory->assertTrusted())->toBeTrue();
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix ownership semantics are not available on Windows.');

it('rejects a default cache directory owned by another user', function () {
    $temporaryDirectory = testHydratorDirectory() . '/foreign-cache-root-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        throw new RuntimeException('Unable to create the foreign-cache test directory.');
    }
    $owner = fileowner($temporaryDirectory);
    if ($owner === false) {
        throw new RuntimeException('Unable to inspect the foreign-cache test directory.');
    }

    $cacheDirectory = new DefaultCacheDirectory($temporaryDirectory, '/application', $owner + 1);
    if (!mkdir($cacheDirectory->path(), 0700)) {
        throw new RuntimeException('Unable to create the foreign default cache directory.');
    }

    expect(fn () => $cacheDirectory->assertTrusted())
        ->toThrow(HydrationException::class, 'not owned by the current operating-system user');
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix ownership semantics are not available on Windows.');

it('rejects a default cache directory accessible by other users', function () {
    $temporaryDirectory = testHydratorDirectory() . '/writable-cache-root-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true)) {
        throw new RuntimeException('Unable to create the writable-cache test directory.');
    }
    $owner = fileowner($temporaryDirectory);
    if ($owner === false) {
        throw new RuntimeException('Unable to inspect the writable-cache test directory.');
    }

    $cacheDirectory = new DefaultCacheDirectory($temporaryDirectory, '/application', $owner);
    if (!mkdir($cacheDirectory->path(), 0700) || !chmod($cacheDirectory->path(), 0777)) {
        throw new RuntimeException('Unable to prepare the writable default cache directory.');
    }

    expect(fn () => $cacheDirectory->assertTrusted())
        ->toThrow(HydrationException::class, 'must be accessible only by its owner');
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix ownership semantics are not available on Windows.');

it('rejects a symbolic link in place of the default cache directory', function () {
    $temporaryDirectory = testHydratorDirectory() . '/linked-cache-root-' . bin2hex(random_bytes(6));
    $linkTarget = testHydratorDirectory() . '/linked-cache-target-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true) || !mkdir($linkTarget, 0700, true)) {
        throw new RuntimeException('Unable to create the linked-cache test directories.');
    }
    $owner = fileowner($temporaryDirectory);
    if ($owner === false) {
        throw new RuntimeException('Unable to inspect the linked-cache test directory.');
    }

    $cacheDirectory = new DefaultCacheDirectory($temporaryDirectory, '/application', $owner);
    if (!symlink($linkTarget, $cacheDirectory->path())) {
        throw new RuntimeException('Unable to create the default-cache symbolic link.');
    }

    try {
        expect(fn () => $cacheDirectory->assertTrusted())
            ->toThrow(HydrationException::class, 'the path must be a real directory');
    } finally {
        if (is_link($cacheDirectory->path()) && !unlink($cacheDirectory->path())) {
            throw new RuntimeException('Unable to remove the default-cache symbolic link.');
        }
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix symbolic-link semantics are not available on Windows.');

it('rejects a shared temporary directory without sticky-bit protection', function () {
    $temporaryDirectory = testHydratorDirectory() . '/unsafe-cache-root-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDirectory, 0700, true) || !chmod($temporaryDirectory, 0777)) {
        throw new RuntimeException('Unable to prepare the unsafe temporary directory.');
    }
    $owner = fileowner($temporaryDirectory);
    if ($owner === false) {
        throw new RuntimeException('Unable to inspect the unsafe temporary directory.');
    }

    $cacheDirectory = new DefaultCacheDirectory($temporaryDirectory, '/application', $owner);

    expect(fn () => $cacheDirectory->assertTrusted())
        ->toThrow(HydrationException::class, 'a shared system temporary directory must have its sticky bit set');
})->skip(PHP_OS_FAMILY === 'Windows', 'Unix sticky-bit semantics are not available on Windows.');
