<?php

declare(strict_types=1);

use MakerMill\HydraType\Mutators\JsonDecode;
use MakerMill\HydraType\Mutators\JsonValue;

it('rejects invalid JSON decoding depths', function (int $depth) {
    expect(fn () => new JsonDecode(depth: $depth))
        ->toThrow(InvalidArgumentException::class, 'JSON decode depth must be between');
})->with([[0], [2_147_483_648]]);

it('rejects invalid bidirectional JSON depths', function (int $depth) {
    expect(fn () => new JsonValue(depth: $depth))
        ->toThrow(InvalidArgumentException::class, 'JSON depth must be between');
})->with([[0], [2_147_483_648]]);
