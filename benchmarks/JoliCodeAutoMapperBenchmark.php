<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- This file is an executable benchmark worker.

use AutoMapper\AutoMapper;
use MakerMill\HydraType\Benchmarks\Fixtures\CompetitorRecord;
use MakerMill\HydraType\Benchmarks\Fixtures\CompetitorRecordInterface;
use MakerMill\HydraType\Benchmarks\Fixtures\PublicCompetitorRecord;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkCase;
use MakerMill\HydraType\Benchmarks\Support\BenchmarkRunner;
use MakerMill\HydraType\Benchmarks\Support\Statistics;

require __DIR__ . '/competitors/automapper/vendor/autoload.php';
require __DIR__ . '/../vendor/autoload.php';

$operations = (int) ($_SERVER['argv'][1] ?? 20_000);
$samples = (int) ($_SERVER['argv'][2] ?? 9);
$recordClass = $_SERVER['argv'][3] ?? CompetitorRecord::class;
$warmupOperations = min(2_000, $operations);

if ($operations < 1 || $samples < 1) {
    throw new InvalidArgumentException('Operations and samples must both be positive integers.');
}
if (!in_array($recordClass, [CompetitorRecord::class, PublicCompetitorRecord::class], true)) {
    throw new InvalidArgumentException('The benchmark target must be a supported competitor fixture.');
}
/** @var class-string<CompetitorRecordInterface> $recordClass */

$data = [
    'id' => 42,
    'userName' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'city' => 'London',
    'active' => true,
];
$expectedChecksum = (new CompetitorRecord(...$data))->checksum();
$autoMapper = AutoMapper::create();
if (!$autoMapper instanceof AutoMapper) {
    throw new RuntimeException('JoliCode AutoMapper did not return its default mapper registry.');
}
$mapper = $autoMapper->getMapper('array', $recordClass);

$map = static function (int $count) use ($mapper, $data): CompetitorRecordInterface {
    for ($i = 0; $i < $count; $i++) {
        /** @var CompetitorRecordInterface $object */
        $object = $mapper->map($data);
    }

    return $object;
};

$timings = BenchmarkRunner::measure(
    [
        'JoliCode AutoMapper 10' => new BenchmarkCase(
            static fn (): CompetitorRecordInterface => $map($operations),
            $operations,
            static function (mixed $object) use ($expectedChecksum, $recordClass): void {
                if (!$object instanceof $recordClass || $object->checksum() !== $expectedChecksum) {
                    throw new RuntimeException('JoliCode AutoMapper produced an invalid benchmark object.');
                }
            },
            static fn (): CompetitorRecordInterface => $map($warmupOperations),
            static function (): void {
                gc_collect_cycles();
            },
        ),
    ],
    $samples,
);

$values = $timings['JoliCode AutoMapper 10'];
echo json_encode(
    [
        'median' => Statistics::median($values),
        'minimum' => min($values),
        'maximum' => max($values),
    ],
    JSON_THROW_ON_ERROR,
);
