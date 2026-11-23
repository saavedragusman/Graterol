<?php

declare(strict_types=1);

use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;

it('BR-01/S1: guarda exactamente 2 decimales y acepta ceros sobrantes', function () {
    expect(Money::fromString('10.12')->toInternalString())->toBe('10.12')
        ->and(Money::fromString('110.0000')->toInternalString())->toBe('110.00')
        ->and(Money::fromString('5')->toInternalString())->toBe('5.00');
});

it('BR-01/S1: rechaza un monto con más de 2 decimales significativos en vez de redondearlo', function () {
    Money::fromString('10.12345');
})->throws(InvalidArgumentException::class);

it('BR-01/S3: Money::rounded redondea half-up al centavo una sola vez', function () {
    expect(Money::rounded('1.8315')->toInternalString())->toBe('1.83')
        ->and(Money::rounded('1.8350')->toInternalString())->toBe('1.84')
        ->and(Money::rounded('1284.505')->toDisplayString())->toBe('1.284,51');
});

it('S1: las sumas son exactas, sin redondeo (3,99 + 3,99 = 7,98)', function () {
    $sum = Money::fromString('3.99')->add(Money::fromString('3.99'));

    expect($sum->toInternalString())->toBe('7.98');
});

it('S3: precio por kg (5,50) × 0,333 kg = 1,8315 → 1,83 y 1,8350 → 1,84', function () {
    expect(UnitAmount::fromString('5.50')->times('0.333')->toInternalString())->toBe('1.83')
        ->and(UnitAmount::fromString('5.5')->times('0.3336364')->toInternalString())->toBe('1.84');
});

it('S4: USD → Bs se convierte sobre el total y se redondea una vez ($0,99 × 918,77 = 909,58)', function () {
    $dual = Money::dualDisplay('0.99', 'USD', '918.77');

    expect($dual['bs'])->toBe('Bs 909,58');
});

it('S5: el costo por kg conserva 4 decimales y × peso crea el monto (12,345 kg × 3,5714 = 44,09)', function () {
    $unit = UnitAmount::fromString('3.5714');

    expect($unit->toStorageString())->toBe('3.5714')
        ->and($unit->times('12.345')->toInternalString())->toBe('44.09')
        ->and(UnitAmount::fromTotal(Money::fromString('44.09'), '12.345')->toStorageString())->toBe('3.5715');
});

it('BR-01: formatea miles con punto y decimales con coma', function () {
    $money = Money::fromString('1234567.8');

    expect($money->toDisplayString())->toBe('1.234.567,80');
});

it('BR-01: suma y resta exactas con 2 decimales', function () {
    $a = Money::fromString('10.12');
    $b = Money::fromString('0.01');

    expect($a->add($b)->toInternalString())->toBe('10.13')
        ->and($a->sub($b)->toInternalString())->toBe('10.11');
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
