<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CloseButcheryOrderAction;
use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\RegisterButcheryOutputWeightAction;
use App\Domain\Butchery\Actions\VoidButcheryOrderAction;
use App\Domain\Catalog\Models\ProductPrice;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// C10/C12/C15: anula una orden de despiece ya abierta o cerrada. Revierte
// butchery_in y, si ya cerró, cada butchery_out; deja auditoría inmutable.

function openSimpleChickenOrder(string $inputWeightKg = '10.000'): array
{
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
        inputWeightKg: $inputWeightKg,
        butcherId: $user->id,
    );

    return compact('user', 'chicken', 'filete', 'batch', 'order');
}

it('voids an open order and reverses its butchery_in movement [C10][C12]', function () {
    $ctx = openSimpleChickenOrder();

    $voided = (new VoidButcheryOrderAction)->execute($ctx['order']->id, 'Error al registrar el lote', $ctx['user']->id);

    expect($voided->status)->toBe('voided')
        ->and($voided->void_reason)->toBe('Error al registrar el lote');

    $netQuantity = InventoryMovement::where('batch_id', $ctx['batch']->id)
        ->get()
        ->reduce(fn (string $carry, $m) => bcadd($carry, (string) $m->quantity, 3), '0.000');

    // El lote vuelve a su saldo original (purchase_in): la anulación revierte
    // exactamente lo que butchery_in había consumido, ni más ni menos.
    expect($netQuantity)->toBe('20.000');
});

it('voids a closed order and reverses every output batch movement too [C10][C12]', function () {
    $ctx = openSimpleChickenOrder();
    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');
    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);
    $outputBatchId = $closed->lines->first()->output_batch_id;

    (new VoidButcheryOrderAction)->execute($closed->id, 'Pesaje incorrecto', $ctx['user']->id);

    $netInput = InventoryMovement::where('batch_id', $ctx['batch']->id)
        ->get()->reduce(fn (string $c, $m) => bcadd($c, (string) $m->quantity, 3), '0.000');
    $netOutput = InventoryMovement::where('batch_id', $outputBatchId)
        ->get()->reduce(fn (string $c, $m) => bcadd($c, (string) $m->quantity, 3), '0.000');

    // El lote de entrada vuelve a su saldo original; el de salida queda en
    // cero porque solo existió dentro de esta orden (butchery_out + su reverso).
    expect($netInput)->toBe('20.000')
        ->and($netOutput)->toBe('0.000');
});

it('leaves an immutable audit trail with who, when and the reason [C15]', function () {
    $ctx = openSimpleChickenOrder();

    (new VoidButcheryOrderAction)->execute($ctx['order']->id, 'Motivo de prueba', $ctx['user']->id);

    $log = AuditLog::where('auditable_type', 'butchery_order')
        ->where('auditable_id', $ctx['order']->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('butchery_order.void')
        ->and($log->user_id)->toBe($ctx['user']->id)
        ->and($log->reason)->toBe('Motivo de prueba')
        ->and($log->before)->toBe(['status' => 'open'])
        ->and($log->after)->toBe(['status' => 'voided']);
});

it('rejects voiding an output batch that already has movements beyond its entry', function () {
    $ctx = openSimpleChickenOrder();
    $line = $ctx['order']->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');
    $closed = (new CloseButcheryOrderAction)->execute($ctx['order']->id, $ctx['user']->id);
    $outputBatchId = $closed->lines->first()->output_batch_id;

    InventoryMovement::create([
        'batch_id' => $outputBatchId,
        'type' => 'sale_out',
        'quantity' => '-1.000',
        'unit_cost' => '1.0000',
        'user_id' => $ctx['user']->id,
        'occurred_at' => now(),
    ]);

    $void = fn () => (new VoidButcheryOrderAction)->execute($closed->id, 'Intento tardío', $ctx['user']->id);

    expect($void)->toThrow(RuntimeException::class);
});

it('rejects voiding an order that is already voided', function () {
    $ctx = openSimpleChickenOrder();
    (new VoidButcheryOrderAction)->execute($ctx['order']->id, 'Primera anulación', $ctx['user']->id);

    $void = fn () => (new VoidButcheryOrderAction)->execute($ctx['order']->id, 'Segunda anulación', $ctx['user']->id);

    expect($void)->toThrow(RuntimeException::class);
});
