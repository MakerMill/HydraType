<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark script.

use MakerMill\HydraType\Benchmarks\Fixtures\PublicCompetitorRecord;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Environment;
use MakerMill\HydraType\Benchmarks\Support\Options;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeyDirect(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        $object->id = (int) $data['id'];
        $object->userName = (string) $data['userName'];
        $object->email = (string) $data['email'];
        $object->city = (string) $data['city'];
        $object->active = (bool) $data['active'];
    }

    return $object;
}

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeyOneCoalesceFallback(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        $object->id = (int) ($data['id'] ?? (
            array_key_exists('id', $data)
                ? null
                : throw new RuntimeException('Missing id.')
        ));
        $object->userName = (string) $data['userName'];
        $object->email = (string) $data['email'];
        $object->city = (string) $data['city'];
        $object->active = (bool) $data['active'];
    }

    return $object;
}

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeyCoalesceFallback(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        $object->id = (int) ($data['id'] ?? (
            array_key_exists('id', $data)
                ? null
                : throw new RuntimeException('Missing id.')
        ));
        $object->userName = (string) ($data['userName'] ?? (
            array_key_exists('userName', $data)
                ? null
                : throw new RuntimeException('Missing userName.')
        ));
        $object->email = (string) ($data['email'] ?? (
            array_key_exists('email', $data)
                ? null
                : throw new RuntimeException('Missing email.')
        ));
        $object->city = (string) ($data['city'] ?? (
            array_key_exists('city', $data)
                ? null
                : throw new RuntimeException('Missing city.')
        ));
        $object->active = (bool) ($data['active'] ?? (
            array_key_exists('active', $data)
                ? null
                : throw new RuntimeException('Missing active.')
        ));
    }

    return $object;
}

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeyCoalesceFallbackWithNaming(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        if (array_key_exists('user_name', $data)) {
            $object->id = (int) ($data['id'] ?? (
                array_key_exists('id', $data)
                    ? null
                    : throw new RuntimeException('Missing id.')
            ));
            $object->userName = (string) ($data['user_name'] ?? (
                array_key_exists('user_name', $data)
                    ? null
                    : throw new RuntimeException('Missing userName.')
            ));
            $object->email = (string) ($data['email'] ?? (
                array_key_exists('email', $data)
                    ? null
                    : throw new RuntimeException('Missing email.')
            ));
            $object->city = (string) ($data['city'] ?? (
                array_key_exists('city', $data)
                    ? null
                    : throw new RuntimeException('Missing city.')
            ));
            $object->active = (bool) ($data['active'] ?? (
                array_key_exists('active', $data)
                    ? null
                    : throw new RuntimeException('Missing active.')
            ));
        } else {
            $object->id = (int) ($data['id'] ?? (
                array_key_exists('id', $data)
                    ? null
                    : throw new RuntimeException('Missing id.')
            ));
            $object->userName = (string) ($data['userName'] ?? (
                array_key_exists('userName', $data)
                    ? null
                    : throw new RuntimeException('Missing userName.')
            ));
            $object->email = (string) ($data['email'] ?? (
                array_key_exists('email', $data)
                    ? null
                    : throw new RuntimeException('Missing email.')
            ));
            $object->city = (string) ($data['city'] ?? (
                array_key_exists('city', $data)
                    ? null
                    : throw new RuntimeException('Missing city.')
            ));
            $object->active = (bool) ($data['active'] ?? (
                array_key_exists('active', $data)
                    ? null
                    : throw new RuntimeException('Missing active.')
            ));
        }
    }

    return $object;
}

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeySeparateGuard(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        if (!array_key_exists('id', $data)) {
            throw new RuntimeException('Missing id.');
        }
        $object->id = (int) $data['id'];
        if (!array_key_exists('userName', $data)) {
            throw new RuntimeException('Missing userName.');
        }
        $object->userName = (string) $data['userName'];
        if (!array_key_exists('email', $data)) {
            throw new RuntimeException('Missing email.');
        }
        $object->email = (string) $data['email'];
        if (!array_key_exists('city', $data)) {
            throw new RuntimeException('Missing city.');
        }
        $object->city = (string) $data['city'];
        if (!array_key_exists('active', $data)) {
            throw new RuntimeException('Missing active.');
        }
        $object->active = (bool) $data['active'];
    }

    return $object;
}

/**
 * @param array{id: mixed, userName: mixed, email: mixed, city: mixed, active: mixed} $data
 */
function requiredKeyTernaryGuard(array $data, int $operations): PublicCompetitorRecord
{
    $object = new PublicCompetitorRecord();
    for ($operation = 0; $operation < $operations; $operation++) {
        $object = new PublicCompetitorRecord();
        $object->id = (int) (
            array_key_exists('id', $data) ? $data['id'] : throw new RuntimeException('Missing id.')
        );
        $object->userName = (string) (
            array_key_exists('userName', $data)
                ? $data['userName']
                : throw new RuntimeException('Missing userName.')
        );
        $object->email = (string) (
            array_key_exists('email', $data) ? $data['email'] : throw new RuntimeException('Missing email.')
        );
        $object->city = (string) (
            array_key_exists('city', $data) ? $data['city'] : throw new RuntimeException('Missing city.')
        );
        $object->active = (bool) (
            array_key_exists('active', $data) ? $data['active'] : throw new RuntimeException('Missing active.')
        );
    }

    return $object;
}

$options = getopt('', ['operations::', 'samples::']);
$operations = Options::integer($options, 'operations', 1_000_000);
$samples = Options::integer($options, 'samples', 15, 3);
$warmupOperations = min(100_000, $operations);
$data = [
    'id' => 42,
    'userName' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'city' => 'London',
    'active' => true,
];
$expectedChecksum = requiredKeyDirect($data, 1)->checksum();
$verify = static function (mixed $result) use ($expectedChecksum): void {
    if (!$result instanceof PublicCompetitorRecord || $result->checksum() !== $expectedChecksum) {
        throw new RuntimeException('Required-key benchmark produced an invalid object.');
    }
};
$prepare = static function (): void {
    gc_collect_cycles();
};

$operationsByCase = [
    'Direct unchecked access' => requiredKeyDirect(...),
    'One ?? fallback' => requiredKeyOneCoalesceFallback(...),
    'Five ?? fallbacks' => requiredKeyCoalesceFallback(...),
    'Five ?? plus naming' => requiredKeyCoalesceFallbackWithNaming(...),
    'Five separate guards' => requiredKeySeparateGuard(...),
    'Five ternary guards' => requiredKeyTernaryGuard(...),
];
$cases = [];
foreach ($operationsByCase as $name => $operation) {
    $cases[$name] = new BenchmarkCase(
        static fn (): PublicCompetitorRecord => $operation($data, $operations),
        $operations,
        $verify,
        static fn (): PublicCompetitorRecord => $operation($data, $warmupOperations),
        $prepare,
    );
}

$measurements = BenchmarkRunner::measure($cases, $samples);
$results = [];
foreach ($measurements as $name => $values) {
    $results[$name] = [
        'median' => Statistics::median($values),
        'minimum' => min($values),
        'maximum' => max($values),
    ];
}
$baseline = $results['Direct unchecked access']['median'];

printf("Required-key lookup benchmark\n");
printf("%s\n", Environment::summary());
printf("%d objects per sample, %d samples, five assigned properties\n\n", $operations, $samples);
printf("%-26s %12s %12s %12s %12s\n", 'Path', 'median ns', 'min ns', 'max ns', 'vs direct');
printf("%s\n", str_repeat('-', 82));
foreach ($results as $name => $result) {
    printf(
        "%-26s %12.1f %12.1f %12.1f %+11.1f%%\n",
        $name,
        $result['median'],
        $result['minimum'],
        $result['maximum'],
        ($result['median'] / $baseline - 1) * 100,
    );
}

$oneFallbackCost = $results['One ?? fallback']['median'] - $baseline;
$fiveFallbackCost = $results['Five ?? fallbacks']['median'] - $baseline;
printf("\nOne ?? fallback delta: %.1f ns/object\n", $oneFallbackCost);
printf(
    "Five ?? fallbacks delta: %.1f ns/object (%.1f ns/property)\n",
    $fiveFallbackCost,
    $fiveFallbackCost / 5,
);
