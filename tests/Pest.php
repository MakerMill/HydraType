<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @template T of object
 *
 * @param class-string<T>      $className
 * @param array<string, mixed> $data
 *
 * @return T
 */
function hydrateObject(
    \MakerMill\HydraType\HydraType $hydra,
    string $className,
    array $data,
): object {
    return objectOf($className, $hydra->hydrate($className, $data));
}

/**
 * @template T of object
 *
 * @param class-string<T> $className
 *
 * @return T
 */
function objectOf(string $className, object $object): object
{
    if (!$object instanceof $className) {
        throw new \RuntimeException("Hydrator returned an unexpected object for '{$className}'.");
    }

    return $object;
}

/**
 * @template T of object
 *
 * @param class-string<T>                 $className
 * @param array<int, array<string, mixed>> $dataSet
 *
 * @return array<int, T>
 */
function hydrateObjects(
    \MakerMill\HydraType\HydraType $hydra,
    string $className,
    array $dataSet,
): array {
    return objectsOf($className, $hydra->hydrateMany($className, $dataSet));
}

/**
 * @template T of object
 *
 * @param class-string<T>   $className
 * @param array<int, object> $objects
 *
 * @return array<int, T>
 */
function objectsOf(string $className, array $objects): array
{
    $typedObjects = [];
    foreach ($objects as $key => $object) {
        if (!$object instanceof $className) {
            throw new \RuntimeException("Hydrator returned an unexpected object for '{$className}'.");
        }

        $typedObjects[$key] = $object;
    }

    return $typedObjects;
}

function readGeneratedFile(string $fileName): string
{
    $contents = file_get_contents($fileName);
    if ($contents === false) {
        throw new \RuntimeException("Unable to read generated file '{$fileName}'.");
    }

    return $contents;
}

function emptyDirectory(string $dirPath): void
{
    // Check if the directory exists
    if (!is_dir($dirPath)) {
        return;
    }

    // Iterate through the directory contents
    foreach (new \DirectoryIterator($dirPath) as $fileInfo) {
        if ($fileInfo->isDot()) {
            continue;
        }

        if ($fileInfo->isDir()) {
            // Recursively delete subdirectories
            emptyDirectory($fileInfo->getRealPath());
            rmdir($fileInfo->getRealPath());
        } else {
            // Delete the file
            unlink($fileInfo->getRealPath());
        }
    }
}

function testHydratorDirectory(): string
{
    return dirname(__DIR__) . '/hydrators';
}

function testConfiguration(?string $hydratorNamespace = null): \MakerMill\HydraType\Configuration
{
    if ($hydratorNamespace !== null) {
        return new \MakerMill\HydraType\Configuration(
            hydratorNamespace: $hydratorNamespace,
            hydratorDirectory: testHydratorDirectory(),
        );
    }

    return new \MakerMill\HydraType\Configuration(hydratorDirectory: testHydratorDirectory());
}

function testHydraType(): \MakerMill\HydraType\HydraType
{
    return new \MakerMill\HydraType\HydraType(testConfiguration());
}

emptyDirectory(testHydratorDirectory());
