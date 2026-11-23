<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\AllocateButcheryCostsAction;

// BR-12: costeo por valor relativo de mercado. Costo_i = C_total × (kg_i·p_i) / Σ(kg_j·p_j).
// La merma no lleva costo; el residuo de redondeo va al corte de mayor valor.

it('splits the cost proportionally to weight × reference price, not to weight alone [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('100.0000', [
        'filete' => ['weight_kg' => '1.000', 'price_per_kg' => '10.00'],
        'muslo' => ['weight_kg' => '1.000', 'price_per_kg' => '5.00'],
    ]);

    expect((float) $allocated['filete'])->toBeGreaterThan((float) $allocated['muslo']);
});

it('gives a cut with zero actual weight no cost at all [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('56.0000', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'piel' => ['weight_kg' => '0.000', 'price_per_kg' => '0.50'],
    ]);

    expect($allocated['piel'])->toBe('0.0000')
        ->and($allocated['filete'])->toBe('56.0000');
});

it('gives the rounding residue to the first cut of highest market value, never leaving a remainder [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('1.0000', [
        'a' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'b' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
        'c' => ['weight_kg' => '1.000', 'price_per_kg' => '1.00'],
    ]);

    $sum = bcadd(bcadd($allocated['a'], $allocated['b'], 4), $allocated['c'], 4);

    expect($sum)->toBe('1.0000')
        ->and($allocated['a'])->toBe('0.3334')
        ->and($allocated['b'])->toBe('0.3333')
        ->and($allocated['c'])->toBe('0.3333');
});

it('the allocated sum always equals the exact total cost [BR-12]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('397.3300', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'muslo' => ['weight_kg' => '6.400', 'price_per_kg' => '3.80'],
        'alas' => ['weight_kg' => '2.000', 'price_per_kg' => '3.50'],
    ]);

    $sum = array_reduce($allocated, fn (string $carry, string $amount) => bcadd($carry, $amount, 4), '0.0000');

    expect($sum)->toBe('397.3300');
});

it('rejects allocating when every cut has zero weight [BR-12]', function () {
    (new AllocateButcheryCostsAction)->execute('56.0000', [
        'filete' => ['weight_kg' => '0.000', 'price_per_kg' => '6.50'],
    ]);
})->throws(InvalidArgumentException::class);

it('reproduces the golden case exactly: 20 kg of chicken at US$ 56,00 [BR-13]', function () {
    $allocated = (new AllocateButcheryCostsAction)->execute('56.0000', [
        'filete' => ['weight_kg' => '4.800', 'price_per_kg' => '6.50'],
        'muslo' => ['weight_kg' => '6.400', 'price_per_kg' => '3.80'],
        'alas' => ['weight_kg' => '2.000', 'price_per_kg' => '3.50'],
        'carapacho' => ['weight_kg' => '4.400', 'price_per_kg' => '1.20'],
        'higado' => ['weight_kg' => '1.200', 'price_per_kg' => '2.50'],
        'piel' => ['weight_kg' => '0.600', 'price_per_kg' => '0.50'],
    ]);

    expect($allocated['filete'])->toBe('24.5738')
        ->and($allocated['muslo'])->toBe('19.1550')
        ->and($allocated['alas'])->toBe('5.5134')
        ->and($allocated['carapacho'])->toBe('4.1586')
        ->and($allocated['higado'])->toBe('2.3629')
        ->and($allocated['piel'])->toBe('0.2363');

    $sum = array_reduce($allocated, fn (string $carry, string $amount) => bcadd($carry, $amount, 4), '0.0000');
    expect($sum)->toBe('56.0000');
});
