<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\StaticAnalysis;

use MakerMill\HydraType\Configuration;
use MakerMill\HydraType\HydraType;
use MakerMill\HydraType\Interfaces\HydratorInterface;
use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

use function PHPStan\Testing\assertType;

function assertGenericTypes(HydraType $hydraType, Configuration $configuration): void
{
    $data = [
        'id' => 1,
        'userName' => 'Ada',
        'password' => 'secret',
    ];

    assertType(SimpleUser::class, $hydraType->hydrate(SimpleUser::class, $data));
    assertType(
        'array<int, ' . SimpleUser::class . '>',
        $hydraType->hydrateMany(SimpleUser::class, [$data]),
    );
    $hydrator = $hydraType->hydrator(SimpleUser::class);
    assertType(
        HydratorInterface::class . '<' . SimpleUser::class . '>',
        $hydrator,
    );
    assertType(SimpleUser::class, $hydrator->hydrate($data));
    assertType(
        HydratorInterface::class . '<' . SimpleUser::class . '>',
        $configuration->getHydratorFactory()->create(SimpleUser::class),
    );
}
