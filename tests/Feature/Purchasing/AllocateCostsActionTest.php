<?php

declare(strict_types=1);

use App\Domain\Purchasing\Actions\AllocateCostsAction;

// BR-07/DEC-02: flete y otros costos se reparten proporcional a los kg recibidos.

it('splits the amount proportionally to the received weight [BR-07]', function () {
    $allocated = (new AllocateCostsAction)->execute('15.00', [
        'media_res' => '83.400',
        'pollo' => '101.200',
    ]);

    $sum = bcadd($allocated['media_res'], $allocated['pollo'], 4);

    expect($sum)->toBe('15.0000')
        ->and((float) $allocated['pollo'])->toBeGreaterThan((float) $allocated['media_res']);
});

it('gives the rounding residue to the line with the most weight [BR-07]', function () {
    $allocated = (new AllocateCostsAction)->execute('1.00', [
        'a' => '1.000',
        'b' => '1.000',
        'c' => '1.000',
    ]);

    expect($allocated['a'])->toBe('0.34')
        ->and($allocated['b'])->toBe('0.33')
        ->and($allocated['c'])->toBe('0.33');
});

it('allocates freight by weight so the parts add up exactly to the total [BR-07][S1]', function () {
    $allocated = (new AllocateCostsAction)->execute('10.00', [
        'a' => '1.000',
        'b' => '1.000',
        'c' => '1.000',
    ]);

    expect($allocated)->toBe(['a' => '3.34', 'b' => '3.33', 'c' => '3.33'])
        ->and(bcadd(bcadd($allocated['a'], $allocated['b'], 2), $allocated['c'], 2))->toBe('10.00');
});

it('the allocated sum always equals the exact total, never more or less [BR-07]', function () {
    $allocated = (new AllocateCostsAction)->execute('397.00', [
        'media_res' => '83.400',
        'pollo' => '101.200',
        'cochino' => '45.750',
    ]);

    $sum = array_reduce($allocated, fn (string $carry, string $amount) => bcadd($carry, $amount, 4), '0.0000');

    expect($sum)->toBe('397.0000');
});

it('returns an empty allocation when there are no lines [BR-07]', function () {
    expect((new AllocateCostsAction)->execute('100.00', []))->toBe([]);
});

it('rejects allocating over a total received weight of zero [BR-07]', function () {
    (new AllocateCostsAction)->execute('100.00', ['a' => '0.000', 'b' => '0.000']);
})->throws(InvalidArgumentException::class);
