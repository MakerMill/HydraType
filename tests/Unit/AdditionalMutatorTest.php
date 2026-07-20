<?php

declare(strict_types=1);

use MakerMill\HydraType\Mutators\Absolute;
use MakerMill\HydraType\Mutators\Clamp;
use MakerMill\HydraType\Mutators\DelimitedString;
use MakerMill\HydraType\Mutators\LeftTrim;
use MakerMill\HydraType\Mutators\RegexReplace;
use MakerMill\HydraType\Mutators\RightTrim;
use MakerMill\HydraType\Mutators\Round;
use MakerMill\HydraType\Mutators\Substring;
use MakerMill\HydraType\Mutators\Trim;
use MakerMill\HydraType\Tests\Fixtures\AdditionalMutatorsRecord;

it('compiles each additional mutator into its expected expression and output type', function (
    object $mutator,
    string $expression,
    string $outputType,
) {
    expect($mutator)->toBeInstanceOf(MakerMill\HydraType\Interfaces\MutatorInterface::class);
    if (!$mutator instanceof MakerMill\HydraType\Interfaces\MutatorInterface) {
        return;
    }

    expect($mutator->compile('$value'))->toBe($expression)
        ->and($mutator->outputType())->toBe($outputType);
})->with([
    'substring with length' => [new Substring(1, 3), 'substr((string) $value, 1, 3)', 'string'],
    'substring to end' => [new Substring(-2), 'substr((string) $value, -2)', 'string'],
    'regex replace' => [
        new RegexReplace('/\s+/', '-'),
        '(preg_replace("/\\\\s+/", "-", (string) $value)'
            . " ?? throw new \\RuntimeException('Regex replacement failed.'))",
        'string',
    ],
    'delimited string' => [new DelimitedString('|'), 'explode("|", (string) $value)', 'array'],
    'round' => [new Round(2, PHP_ROUND_HALF_EVEN), 'round((float) $value, 2, 3)', 'float'],
    'clamp' => [new Clamp(0.5, 10.5), 'max(0.5, min(10.5, (float) $value))', 'float'],
    'absolute' => [new Absolute(), 'abs((float) $value)', 'float'],
]);

it('hydrates and extracts the additional mutators', function () {
    $hydra = testHydraType();
    $record = hydrateObject($hydra, AdditionalMutatorsRecord::class, [
        'segment' => 'abcdef',
        'slug' => 'Hydra   Type',
        'tags' => 'fast|compiled',
        'price' => '1.235',
        'percentage' => '120',
        'distance' => '-12.5',
    ]);

    expect($record->values())->toBe([
        'segment' => 'bcd',
        'slug' => 'Hydra-Type',
        'tags' => ['fast', 'compiled'],
        'price' => 1.24,
        'percentage' => 100,
        'distance' => 12.5,
    ])->and($hydra->extract($record))->toBe([
        'segment' => 'bcd',
        'slug' => 'Hydra-Type',
        'tags' => 'fast|compiled',
        'price' => 1.24,
        'percentage' => 100,
        'distance' => 12.5,
    ]);
});

it('rejects invalid additional mutator configuration', function (Closure $factory, string $message) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'invalid regex' => [fn () => new RegexReplace('/[/', ''), 'valid PCRE pattern'],
    'empty separator' => [fn () => new DelimitedString(''), 'separator'],
    'invalid rounding mode' => [fn () => new Round(mode: -1), 'PHP_ROUND_HALF_*'],
    'reversed clamp' => [fn () => new Clamp(2, 1), 'minimum'],
    'empty trim characters' => [fn () => new Trim(''), 'character list'],
    'empty left trim characters' => [fn () => new LeftTrim(''), 'character list'],
    'empty right trim characters' => [fn () => new RightTrim(''), 'character list'],
]);

it('surfaces regex replacement failures', function () {
    $mutator = new RegexReplace('/./u', 'x');
    $expression = $mutator->compile('$value');
    $replace = eval('return static fn (string $value): string => ' . $expression . ';');
    if (!$replace instanceof Closure) {
        throw new RuntimeException('Unable to compile regex replacement test expression.');
    }

    expect(fn () => $replace("\xFF"))->toThrow(RuntimeException::class, 'Regex replacement failed.');
});
