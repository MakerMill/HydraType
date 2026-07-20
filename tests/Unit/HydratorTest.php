<?php

use MakerMill\HydraType\Tests\Fixtures\SimpleUser;

it('hydrates an object through a generated hydrator', function () {
    $userHydrator = testConfiguration()->getHydratorFactory()->create(SimpleUser::class);

    $dbRow = [
        'id' => 1,
        'user_name' => 'John Doe',
        'password' => 'password',
    ];

    $user = objectOf(SimpleUser::class, $userHydrator->hydrate($dbRow));
    expect($user->getId())->toBe(1)
        ->and($user->getUserName())->toBe('John Doe')
        ->and($user->getPassword())->toBe('password');
});

it('hydrates multiple objects through a generated hydrator', function () {
    $userHydrator = testConfiguration()->getHydratorFactory()->create(SimpleUser::class);

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

    $users = objectsOf(SimpleUser::class, $userHydrator->hydrateMany($dbRows));
    expect($users[0]->getId())->toBe(1)
        ->and($users[0]->getUserName())->toBe('John Doe')
        ->and($users[0]->getPassword())->toBe('password')
        ->and($users[1]->getId())->toBe(2)
        ->and($users[1]->getUserName())->toBe('Jane Doe')
        ->and($users[1]->getPassword())->toBe('password');
});
