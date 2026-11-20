<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\AllocateButcheryCostsAction;

// BR-12: costeo por valor relativo de mercado. Costo_i = C_total × (kg_i·p_i) / Σ(kg_j·p_j).
// La merma no lleva costo; el residuo de redondeo va al corte de mayor valor.

it('splits the cost proportionally to weight × reference price, not to weight alone [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('100.00', [
        'filete' => ['weight_kg' => '1.000', 'price_per_kg' => '10.00'],
        'muslo' => ['weight_kg' => '1.000', 'price_per_kg' => '5.00'],
    ]);

    expect((float) $allocated['filete'])->toBeGreaterThan((float) $allocated['muslo']);
});

it('gives a cut with zero actual weight no cost at all [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('56.00', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'piel' => ['weight_kg' => '0.000', 'price_per_kg' => '0.50'],
    ]);

    expect($allocated['piel'])->toBe('0.00')
        ->and($allocated['filete'])->toBe('56.00');
});

it('gives the rounding residue to the first cut of highest market value, never leaving a remainder [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('1.00', [
        'a' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'b' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'c' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
    ]);

    $sum = bcadd(bcadd($allocated['a'], $allocated['b'], 2), $allocated['c'], 2);

    expect($sum)->toBe('1.00')
        ->and($allocated['a'])->toBe('0.34')
        ->and($allocated['b'])->toBe('0.33')
        ->and($allocated['c'])->toBe('0.33');
});

it('the allocated sum always equals the exact total cost [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('397.33', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'muslo' => ['weight_kg' => '6.400', 'price_per_kg' => '3.80'],
        'alas' => ['weight_kg' => '2.000', 'price_per_kg' => '3.50'],
    ]);

    $sum = array_reduce($allocated, fn (string $carry, string $amount) => bcadd($carry, $amount, 2), '0.00');

    expect($sum)->toBe('397.33');
});

it('the two-step split reproduces the golden case: 4 decimals first, then cents [BR-12][BR-13]', function () {
    // Muslo exacto 19,154992…: a 4 decimales 19,1550 y recién entonces 19,16 (un solo paso daría 19,15).
    $allocated = (new AllocateButcheryCostsAction)->execute('56.00', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'muslo' => ['weight_kg' => '6.400', 'price_per_kg' => '3.80'],
        'alas' => ['weight_kg' => '2.000', 'price_per_kg' => '3.50'],
        'carapacho' => ['weight_kg' => '4.400', 'price_per_kg' => '1.20'],
        'higado' => ['weight_kg' => '1.200', 'price_per_kg' => '2.50'],
        'piel' => ['weight_kg' => '0.600', 'price_per_kg' => '0.50'],
    ]);

    expect($allocated['muslo'])->toBe('19.16')
        ->and($allocated['filete'])->toBe('24.57');
});

it('keeps the sum exact when both the 4-decimal and the cent residues are non-zero [BR-12]', function () {
    // 3 cortes iguales con $0,10: partes 0,0333 ×3 (residuo4 +0,0001 al primero) → 0,0334 / 0,0333 / 0,0333;
    // a centavos 0,03 ×3 → residuo2 +0,01 al primero.
    $allocated = (new AllocateButcheryCostsAction)->execute('0.10', [
        'a' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'b' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'c' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
    ]);

    expect($allocated)->toBe(['a' => '0.04', 'b' => '0.03', 'c' => '0.03'])
        ->and(bcadd(bcadd($allocated['a'], $allocated['b'], 2), $allocated['c'], 2))->toBe('0.10');
});

it('rejects allocating when every cut has zero weight [BR-12]', function () {
    (new AllocateButcheryCostsAction)->execute('56.00', [
        'filete' => ['weight_kg' => '0.000', 'price_per_kg' => '6.50'],
    ]);
})->throws(InvalidArgumentException::class);

it('reproduces the golden case exactly: 20 kg of chicken at US$ 56,00 [BR-13]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('56.00', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'muslo' => ['weight_kg' => '6.400', 'price_per_kg' => '3.80'],
        'alas' => ['weight_kg' => '2.000', 'price_per_kg' => '3.50'],
        'carapacho' => ['weight_kg' => '4.400', 'price_per_kg' => '1.20'],
        'higado' => ['weight_kg' => '1.200', 'price_per_kg' => '2.50'],
        'piel' => ['weight_kg' => '0.600', 'price_per_kg' => '0.50'],
    ]);

    expect($allocated['filete'])->toBe('24.57')
        ->and($allocated['muslo'])->toBe('19.16')
        ->and($allocated['alas'])->toBe('5.51')
        ->and($allocated['carapacho'])->toBe('4.16')
        ->and($allocated['higado'])->toBe('2.36')
        ->and($allocated['piel'])->toBe('0.24');

    $sum = array_reduce($allocated, fn (string $carry, string $amount) => bcadd($carry, $amount, 2), '0.00');
    expect($sum)->toBe('56.00');
});
