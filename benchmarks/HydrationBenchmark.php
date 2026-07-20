<?php

declare(strict_types=1);

use MakerMill\HydraType\Benchmarks\Fixtures\SimpleClass;
use MakerMill\HydraType\Configuration;

require __DIR__ . '/../vendor/autoload.php';

$hydratorDirectory = __DIR__ . '/../hydrators';

// Arrange: Set up the factory
$configuration = new Configuration(hydratorDirectory: $hydratorDirectory);
$factory = $configuration->getHydratorFactory();

// Act: Generate the hydrator for the benchmark class
$userHydrator = $factory->create(SimpleClass::class);

$dbRows = [
    [
        'id' => 1,
        'user_name' => 'John Doe',
        'password' => 'password',
    ],
    [
        'id' => 2,
        'user_name' => 'Jane Doe',
        'password' => 'password',
    ],
];

$users = [];
$total = 5000;
$faker = Faker\Factory::create();
for ($i = 0; $i < $total; $i++) {
    $users[] = [
       'id' => $faker->unique()->randomNumber(),
       'userName' => $faker->name(),
       'password' => $faker->password(),
       'type' => 'USER',
       'active' => '1',
    ];
}

$totalTime = 0;
for ($i = 0; $i < 300; $i++) {
    $start = hrtime(true);
    $result = $userHydrator->hydrateMany($users);
    $end = hrtime(true);
    $totalTime += $end - $start;
}
echo "Time: " . ($totalTime / 300) / 1e+6 . "ms\n";
