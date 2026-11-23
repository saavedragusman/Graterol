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

it('BR-01: display muestra 2 decimales y siempre la moneda (Bs o $)', function () {
    expect(Money::display('1284.5049', 'VES'))->toBe('Bs 1.284,50')
        ->and(Money::display('56', 'USD'))->toBe('$ 56,00')
        ->and(Money::display(null, 'VES'))->toBe('—');
});

it('dualDisplay: un monto en USD se convierte a Bs multiplicando por la tasa', function () {
    expect(Money::dualDisplay('1234.5', 'USD', '85.5000'))->toBe([
        'usd' => '$ 1.234,50',
        'bs' => 'Bs 105.549,75',
        'rate' => 'Bs 85,50',
    ]);
});

it('dualDisplay: un monto en VES se convierte a USD dividiendo entre la tasa', function () {
    expect(Money::dualDisplay('8550', 'VES', '85.5000'))->toBe([
        'usd' => '$ 100,00',
        'bs' => 'Bs 8.550,00',
        'rate' => 'Bs 85,50',
    ]);
});

it('dualDisplay: sin tasa (null, vacía o cero) solo devuelve el lado original', function () {
    $usd = ['usd' => '$ 9,50', 'bs' => null, 'rate' => null];
    $ves = ['usd' => null, 'bs' => 'Bs 9,50', 'rate' => null];

    expect(Money::dualDisplay('9.5', 'USD', null))->toBe($usd)
        ->and(Money::dualDisplay('9.5', 'USD', '0.0000'))->toBe($usd)
        ->and(Money::dualDisplay('9.5', 'USD', ''))->toBe($usd)
        ->and(Money::dualDisplay('9.5', 'VES', null))->toBe($ves);
});

it('dualDisplay: redondea a 2 decimales en pantalla con half-up', function () {
    // 1.005 × 85.5 = 85.9275 → 85,93; 10 ÷ 3 = 3.3333 → 3,33.
    expect(Money::dualDisplay('1.005', 'USD', '85.5')['bs'])->toBe('Bs 85,93')
        ->and(Money::dualDisplay('10', 'VES', '3')['usd'])->toBe('$ 3,33');
});

it('dualDisplay: sin monto no muestra nada', function () {
    expect(Money::dualDisplay(null, 'USD', '85.5'))->toBe(['usd' => null, 'bs' => null, 'rate' => null]);
});
