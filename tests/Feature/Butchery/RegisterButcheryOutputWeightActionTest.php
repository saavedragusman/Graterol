<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\RegisterButcheryOutputWeightAction;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-09: el carnicero pesa cada corte; puede ser 0.

it('saves the actual weight of a cut, including zero [BR-09]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');
    $muslo = createCut('Muslo');

    (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '3.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '60.00'],
            ['cut_product_id' => $muslo->id, 'expected_yield_percent' => '37.00'],
        ],
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
        inputWeightKg: '20.000',
        butcherId: $user->id,
    );

    $filteLine = $order->lines->firstWhere('cut_product_id', $filete->id);
    $musloLine = $order->lines->firstWhere('cut_product_id', $muslo->id);

    $updated = (new RegisterButcheryOutputWeightAction)->execute($filteLine->id, '12.300');
    expect($updated->actual_weight_kg)->toBe('12.300');

    $updatedZero = (new RegisterButcheryOutputWeightAction)->execute($musloLine->id, '0.000');
    expect($updatedZero->actual_weight_kg)->toBe('0.000');
});

it('rejects registering weight on a line of a closed or voided order [C12]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

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
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $batch->id,
        inputWeightKg: '10.000',
        butcherId: $user->id,
    );

    $order->update(['status' => 'closed']);
    $line = $order->lines->first();

    $register = fn () => (new RegisterButcheryOutputWeightAction)->execute($line->id, '5.000');

    expect($register)->toThrow(RuntimeException::class);
});

it('rejects a negative weight [BR-09]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

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
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
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

    $register = fn () => (new RegisterButcheryOutputWeightAction)->execute($line->id, '-1.000');

    expect($register)->toThrow(InvalidArgumentException::class);
});
