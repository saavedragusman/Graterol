<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\UpdateButcheryTemplateAction;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-09: la orden registra el lote, los kg de entrada y el carnicero, y
// muestra los kg esperados por corte (plantilla congelada, DEC-03).
// DEC-04: consume el lote (butchery_in) al abrir, no al cerrar.
// DEC-07: puede ser parcial, valida saldo disponible del lote.

function openChickenBatch(string $weightKg = '20.000'): array
{
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');
    $muslo = createCut('Muslo');

    $template = (new CreateButcheryTemplateAction)->execute(
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
        receivedWeightKg: $weightKg,
        totalCost: '56.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    return compact('user', 'chicken', 'filete', 'muslo', 'template', 'batch');
}

it('freezes the active template lines into the order with their expected kg [BR-09][DEC-03]', function () {
    $ctx = openChickenBatch();

    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '20.000',
        butcherId: $ctx['user']->id,
    );

    expect($order->status)->toBe('open')
        ->and($order->input_weight_kg)->toBe('20.000')
        ->and($order->expected_shrinkage_percent)->toBe('3.00')
        ->and($order->lines)->toHaveCount(2);

    $filete = $order->lines->firstWhere('cut_product_id', $ctx['filete']->id);
    expect($filete->expected_yield_percent)->toBe('60.00')
        ->and($filete->expected_weight_kg)->toBe('12.000');
});

it('registers the butchery_in movement against the input batch right away [DEC-04][C10]', function () {
    $ctx = openChickenBatch();

    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '20.000',
        butcherId: $ctx['user']->id,
    );

    $movement = InventoryMovement::where('batch_id', $ctx['batch']->id)
        ->where('type', 'butchery_in')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe('-20.000')
        ->and($movement->reference_type)->toBe('butchery_order')
        ->and($movement->reference_id)->toBe($order->id);
});

it('allows opening with less than the full batch and leaves the remainder available [DEC-07]', function () {
    $ctx = openChickenBatch('20.000');

    $firstOrder = (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '12.000',
        butcherId: $ctx['user']->id,
    );

    $secondOrder = (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '8.000',
        butcherId: $ctx['user']->id,
    );

    expect($firstOrder->input_weight_kg)->toBe('12.000')
        ->and($secondOrder->input_weight_kg)->toBe('8.000');
});

it('rejects opening for more than the available balance of the batch [DEC-07]', function () {
    $ctx = openChickenBatch('20.000');

    $open = fn () => (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '25.000',
        butcherId: $ctx['user']->id,
    );

    expect($open)->toThrow(RuntimeException::class);
});

it('rejects opening a batch whose product has no active template [BR-08]', function () {
    $user = User::factory()->create();
    $rawMaterial = createRawMaterial('Res');

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 2,
        productId: $rawMaterial->id,
        pieces: null,
        receivedWeightKg: '10.000',
        totalCost: '100.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $open = fn () => (new OpenButcheryOrderAction)->execute(
        batchId: $batch->id,
        inputWeightKg: '10.000',
        butcherId: $user->id,
    );

    expect($open)->toThrow(RuntimeException::class);
});

it('keeps an already open order intact when the template is edited afterwards [DEC-03]', function () {
    $ctx = openChickenBatch();
    $order = (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '20.000',
        butcherId: $ctx['user']->id,
    );
    $originalFileteLine = $order->lines->firstWhere('cut_product_id', $ctx['filete']->id);

    (new UpdateButcheryTemplateAction)->execute(
        templateId: $ctx['template']->id,
        name: 'Pollo recalibrado',
        expectedShrinkagePercent: '10.00',
        lines: [
            ['cut_product_id' => $ctx['filete']->id, 'expected_yield_percent' => '50.00'],
            ['cut_product_id' => $ctx['muslo']->id, 'expected_yield_percent' => '40.00'],
        ],
    );

    expect($order->refresh()->lines->firstWhere('cut_product_id', $ctx['filete']->id)->expected_yield_percent)
        ->toBe($originalFileteLine->expected_yield_percent)
        ->and($originalFileteLine->expected_yield_percent)->toBe('60.00');
});

it('rejects opening with zero or negative input weight [BR-09]', function () {
    $ctx = openChickenBatch();

    $open = fn () => (new OpenButcheryOrderAction)->execute(
        batchId: $ctx['batch']->id,
        inputWeightKg: '0.000',
        butcherId: $ctx['user']->id,
    );

    expect($open)->toThrow(InvalidArgumentException::class);
});

it('rejects opening a batch whose product is not raw material [BR-04][BR-08]', function () {
    $user = User::factory()->create();
    $cut = createCut('Filete suelto');

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 3,
        productId: $cut->id,
        pieces: null,
        receivedWeightKg: '5.000',
        totalCost: '50.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $open = fn () => (new OpenButcheryOrderAction)->execute(
        batchId: $batch->id,
        inputWeightKg: '5.000',
        butcherId: $user->id,
    );

    expect($open)->toThrow(InvalidArgumentException::class);
});
