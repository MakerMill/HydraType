<?php

declare(strict_types=1);

use MakerMill\HydraType\Compiler\PhpLiteral;

it('emits readable PHP string escapes without a formatting pass', function () {
    expect(PhpLiteral::string(" \t\n\r\0\x0B\f\e\\\"\$\x7F"))->toBe(
        '" \\t\\n\\r\\x00\\v\\f\\e\\\\\\"\\$\\x7F"',
    );
});

it('emits other control bytes as fixed-width hexadecimal escapes', function () {
    expect(PhpLiteral::string("\x01A"))->toBe('"\\x01A"');
});

it('emits scalar PHP literals', function () {
    expect(PhpLiteral::value('value'))->toBe('"value"')
        ->and(PhpLiteral::value(42))->toBe('42')
        ->and(PhpLiteral::value(12.5))->toBe('12.5')
        ->and(PhpLiteral::value(true))->toBe('true')
        ->and(PhpLiteral::value(false))->toBe('false');
});
