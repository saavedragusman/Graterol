<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CloseButcheryOrderAction;
use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\RegisterButcheryOutputWeightAction;
use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductPrice;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-10/BR-11/BR-12: cierra la orden, costea por valor relativo de mercado
// y calcula la desviación frente a la plantilla.

/**
 * @param  array<string, string>  $detailPrices  nombre del corte => precio detal US$/kg
 * @return array{user: User, chicken: Product, cuts: array<string, Product>, template: ButcheryTemplate, batch: Batch, order: ButcheryOrder}
 */
function openPricedChickenOrder(string $inputWeightKg, array $cutYields, array $detailPrices, string $totalCost): array
{
    $user = User::factory()->create();
    $chicken = createRawMaterial();

    $cuts = [];
    $lines = [];
    foreach ($cutYields as $name => $yieldPercent) {
        $cut = createCut($name);
        $cuts[$name] = $cut;
        $lines[] = ['cut_product_id' => $cut->id, 'expected_yield_percent' => $yieldPercent];

        if (isset($detailPrices[$name])) {
            ProductPrice::create([
                'product_id' => $cut->id,
                'price_list' => 'detail',
                'amount' => $detailPrices[$name],
                'currency' => 'USD',
            ]);
        }
    }

    $shrinkage = bcsub('100.00', array_reduce($cutYields, fn ($c, $y) => bcadd($c, $y, 2), '0.00'), 2);

    $template = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: $shrinkage,
        lines: $lines,
        createdByUserId: $user->id,
    );

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 1,
        productId: $chicken->id,
        pieces: null,
        receivedWeightKg: $inputWeightKg,
        totalCost: $totalCost,
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $batch->id,
        inputWeightKg: $inputWeightKg,
        butcherId: $user->id,
    );

    return compact('user', 'chicken', 'cuts', 'template', 'batch', 'order');
}

it('reproduces the golden case exactly: 20 kg of chicken at US$ 56,00 [BR-13]', function () {
    $ctx = openPricedChickenOrder(
        inputWeightKg: '20.000',
        cutYields: [
            'Filete de pechuga' => '24.00',
            'Muslo' => '32.00',
            'Alas' => '10.00',
            'Carapacho' => '22.00',
            'Hígado y molleja' => '6.00',
            'Piel y grasa' => '3.00',
        ],
        detailPrices: [
            'Filete de pechuga' => '6.50',
            'Muslo' => '3.80',
            'Alas' => '3.50',
            'Carapacho' => '1.20',
            'Hígado y molleja' => '2.50',
            'Piel y grasa' => '0.50',
        ],
        totalCost: '56.0000',
    );

    $actualByName = [
        'Filete de pechuga' => '4.800',
        'Muslo' => '6.400',
        'Alas' => '2.000',
        'Carapacho' => '4.400',
        'Hígado y molleja' => '1.200',
        'Piel y grasa' => '0.600',
    ];

    foreach ($actualByName as $name => $weight) {
        $line = $ctx['order']->lines->firstWhere('cut_product_id', $ctx['cuts'][$name]->id);
        (new RegisterButcheryOutputWeightAction)->execute($line->id, $weight);
    }

    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    $byName = fn (string $name) => $closed->lines->firstWhere('cut_product_id', $ctx['cuts'][$name]->id);

    expect($closed->status)->toBe('closed')
        ->and($closed->actual_shrinkage_kg)->toBe('0.600')
        ->and($byName('Filete de pechuga')->allocated_cost)->toBe('24.57')
        ->and($byName('Muslo')->allocated_cost)->toBe('19.16')
        ->and($byName('Alas')->allocated_cost)->toBe('5.51')
        ->and($byName('Carapacho')->allocated_cost)->toBe('4.16')
        ->and($byName('Hígado y molleja')->allocated_cost)->toBe('2.36')
        ->and($byName('Piel y grasa')->allocated_cost)->toBe('0.24');

    $sum = array_reduce(
        $closed->lines->all(),
        fn (string $carry, $line) => bcadd($carry, (string) $line->allocated_cost, 2),
        '0.00',
    );
    expect($sum)->toBe('56.00');
});

it('displays the golden case costs exactly as BR-13 states them, in two decimals [BR-13]', function () {
    $ctx = openPricedChickenOrder(
        inputWeightKg: '20.000',
        cutYields: [
            'Filete de pechuga' => '24.00',
            'Muslo' => '32.00',
            'Alas' => '10.00',
            'Carapacho' => '22.00',
            'Hígado y molleja' => '6.00',
            'Piel y grasa' => '3.00',
        ],
        detailPrices: [
            'Filete de pechuga' => '6.50',
            'Muslo' => '3.80',
            'Alas' => '3.50',
            'Carapacho' => '1.20',
            'Hígado y molleja' => '2.50',
            'Piel y grasa' => '0.50',
        ],
        totalCost: '56.0000',
    );

    $actualByName = [
        'Filete de pechuga' => '4.800',
        'Muslo' => '6.400',
        'Alas' => '2.000',
        'Carapacho' => '4.400',
        'Hígado y molleja' => '1.200',
        'Piel y grasa' => '0.600',
    ];

    foreach ($actualByName as $name => $weight) {
        $line = $ctx['order']->lines->firstWhere('cut_product_id', $ctx['cuts'][$name]->id);
        (new RegisterButcheryOutputWeightAction)->execute($line->id, $weight);
    }

    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);
    $byName = fn (string $name) => $closed->lines->firstWhere('cut_product_id', $ctx['cuts'][$name]->id);
    $display = fn (string $name) => Money::fromString((string) $byName($name)->allocated_cost)->toDisplayString();

    expect($display('Filete de pechuga'))->toBe('24,57')
        ->and($display('Muslo'))->toBe('19,16')
        ->and($display('Alas'))->toBe('5,51')
        ->and($display('Carapacho'))->toBe('4,16')
        ->and($display('Hígado y molleja'))->toBe('2,36')
        ->and($display('Piel y grasa'))->toBe('0,24');
});

it('rejects closing when a line has no weight registered yet [BR-10]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], ['Filete' => '5.00'], '20.0000');

    $close = fn () => (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    expect($close)->toThrow(RuntimeException::class);
});

it('rejects closing when the outputs exceed the input weight [BR-10]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], ['Filete' => '5.00'], '20.0000');

    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '11.000');

    $close = fn () => (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    expect($close)->toThrow(RuntimeException::class);
});

it('rejects closing when a cut with real weight has no detail price configured [DEC-01]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], [], '20.0000');

    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');

    $close = fn () => (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    expect($close)->toThrow(RuntimeException::class);
});

it('computes the deviation in points against the expected yield [BR-11]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], ['Filete' => '5.00'], '20.0000');

    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');

    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    expect($closed->lines->first()->deviation_percent)->toBe('-10.00');
});

it('creates an output batch and a butchery_out movement for each cut with real weight [BR-12][C10]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], ['Filete' => '5.00'], '20.0000');

    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');

    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    $closedLine = $closed->lines->first();
    expect($closedLine->output_batch_id)->not->toBeNull();

    $movement = InventoryMovement::where('batch_id', $closedLine->output_batch_id)
        ->where('type', 'butchery_out')
        ->first();
    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe('9.000');
});

it('allocates cost proportional to the input weight of this order, not the whole batch [BR-12][DEC-07]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    ProductPrice::create(['product_id' => $filete->id, 'price_list' => 'detail', 'amount' => '5.00', 'currency' => 'USD']);

    (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 1,
        productId: $chicken->id,
        pieces: null,
        receivedWeightKg: '20.000',
        totalCost: '56.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $batch->id,
        inputWeightKg: '10.000',
        butcherId: $user->id,
    );

    $line = $order->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '10.000');

    $closed = (new CloseButcheryOrderAction)->execute($order->id, $user->id);

    // unit_cost del lote = 56/20 = 2.8000; costo de esta orden = 2.80 * 10 = 28.0000, no 56.0000.
    expect($closed->lines->first()->allocated_cost)->toBe('28.00');
});

it('rejects closing an order that is not open [C12]', function () {
    $ctx = openPricedChickenOrder('10.000', ['Filete' => '100.00'], ['Filete' => '5.00'], '20.0000');

    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');
    (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    $close = fn () => (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);

    expect($close)->toThrow(RuntimeException::class);
});
