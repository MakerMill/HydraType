<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use MakerMill\HydraType\HydraType;

final class PackagedUser
{
    private int $id = 0;

    public function id(): int
    {
        return $this->id;
    }
}

$user = (new HydraType())->hydrate(PackagedUser::class, ['id' => '42']);

if ($user->id() !== 42) {
    throw new RuntimeException('The packaged consumer smoke test failed.');
}
