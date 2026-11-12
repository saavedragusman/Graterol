<?php

declare(strict_types=1);

use App\Support\ValueObjects\Money;

it('BR-01: guarda 4 decimales internos aunque se le pasen más', function () {
    $money = Money::fromString('10.12345');

    expect($money->toInternalString())->toBe('10.1235');
});

it('BR-01: muestra 2 decimales en formato es-VE con redondeo half-up', function () {
    $money = Money::fromString('1284.505');

    expect($money->toDisplayString())->toBe('1.284,51');
});

it('BR-01: formatea miles con punto y decimales con coma', function () {
    $money = Money::fromString('1234567.8');

    expect($money->toDisplayString())->toBe('1.234.567,80');
});

it('BR-01: suma y resta preservando 4 decimales internos', function () {
    $a = Money::fromString('10.1234');
    $b = Money::fromString('0.0001');

    expect($a->add($b)->toInternalString())->toBe('10.1235')
        ->and($a->sub($b)->toInternalString())->toBe('10.1233');
});

it('BR-01: compara y detecta montos negativos', function () {
    $zero = Money::zero();
    $negative = Money::fromString('-5.00');

    expect($negative->isNegative())->toBeTrue()
        ->and($zero->isNegative())->toBeFalse()
        ->and($negative->compareTo($zero))->toBe(-1);
});
