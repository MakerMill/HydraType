<?php

declare(strict_types=1);

use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? null;
if (
    !is_array($arguments)
    || !isset($arguments[1], $arguments[2], $arguments[3])
    || !is_string($arguments[1])
    || !is_string($arguments[2])
    || !is_string($arguments[3])
) {
    throw new InvalidArgumentException('Expected cache directory, start file, and namespace arguments.');
}

$cacheDirectory = $arguments[1];
$startFile = $arguments[2];
$namespace = $arguments[3];

$deadline = microtime(true) + 10;
while (!is_file($startFile)) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Timed out waiting for the concurrency-test start signal.');
    }
    usleep(1_000);
}

$configuration = new Configuration(
    hydratorNamespace: $namespace,
    hydratorDirectory: $cacheDirectory,
);
$object = $configuration->getHydratorFactory()->create(SimpleUser::class)->hydrate([
    'id' => 42,
    'userName' => 'Concurrent',
    'password' => 'secret',
]);

if (
    $object->getId() !== 42
    || $object->getUserName() !== 'Concurrent'
    || $object->getPassword() !== 'secret'
) {
    throw new RuntimeException('Concurrent hydration produced an invalid object.');
}
