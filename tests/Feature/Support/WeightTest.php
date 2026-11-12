<?php

declare(strict_types=1);

use App\Support\ValueObjects\Weight;

it('BR-01: guarda el peso en kg con 3 decimales', function () {
    $weight = Weight::fromString('20.12345');

    expect($weight->toStorageString())->toBe('20.123');
});

it('BR-01: muestra el peso en formato es-VE con 3 decimales', function () {
    $weight = Weight::fromString('1284.5');

    expect($weight->toDisplayString())->toBe('1.284,500');
});

it('BR-01: suma y resta pesos preservando 3 decimales', function () {
    $a = Weight::fromString('20.125');
    $b = Weight::fromString('0.005');

    expect($a->add($b)->toStorageString())->toBe('20.130')
        ->and($a->sub($b)->toStorageString())->toBe('20.120');
});

it('BR-01: compara y detecta pesos negativos', function () {
    $zero = Weight::zero();
    $negative = Weight::fromString('-1.5');

    expect($negative->isNegative())->toBeTrue()
        ->and($zero->isNegative())->toBeFalse()
        ->and($negative->compareTo($zero))->toBe(-1);
});
