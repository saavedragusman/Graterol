<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Queries\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// BR-14: stock = Σ inventory_movements.quantity por lote; nunca se guarda saldo.

function stockBatch(?int $productId, array $movements): Batch
{
    $batch = Batch::factory()->create(['product_id' => $productId]);
    $userId = User::factory()->create()->id;

    foreach ($movements as [$type, $quantity]) {
        InventoryMovement::create([
            'batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => '1.0000',
            'user_id' => $userId,
            'occurred_at' => now(),
        ]);
    }

    return $batch;
}

it('sums every movement of the batch including negatives and voids [BR-14]', function () {
    $batch = stockBatch(createRawMaterial()->id, [
        ['purchase_in', '100.000'],
        ['butchery_in', '-30.250'],
        ['butchery_void', '10.000'],
        ['purchase_void', '-5.000'],
    ]);

    expect((new StockBalance)->forBatch($batch->id))->toBe('74.750');
});

it('returns 0.000 for a batch without movements [BR-14]', function () {
    $batch = stockBatch(createRawMaterial()->id, []);

    expect((new StockBalance)->forBatch($batch->id))->toBe('0.000');
});

it('sums only the batches of the requested product [BR-14]', function () {
    $chicken = createRawMaterial('Pollo');
    $other = createRawMaterial('Res');

    stockBatch($chicken->id, [['purchase_in', '10.000']]);
    stockBatch($chicken->id, [['purchase_in', '5.500'], ['butchery_in', '-1.000']]);
    stockBatch($other->id, [['purchase_in', '99.000']]);

    $balance = new StockBalance;

    expect($balance->forProduct($chicken->id))->toBe('14.500')
        ->and($balance->forProduct($other->id))->toBe('99.000')
        ->and($balance->forProduct(createRawMaterial('Sin lotes')->id))->toBe('0.000');
});

it('lists only batches with a positive balance as 3-decimal strings [BR-14]', function () {
    $product = createRawMaterial();
    $active = stockBatch($product->id, [['purchase_in', '8.000'], ['butchery_in', '-2.500']]);
    stockBatch($product->id, [['purchase_in', '4.000'], ['butchery_in', '-4.000']]);
    stockBatch($product->id, [['purchase_in', '1.000'], ['butchery_in', '-3.000']]);
    stockBatch($product->id, []);

    $list = (new StockBalance)->activeBatches();

    expect($list)->toHaveCount(1)
        ->and($list->first()->id)->toBe($active->id)
        ->and($list->first()->getAttribute('balance'))->toBe('5.500');
});

it('filters the active list by product [BR-14]', function () {
    $chicken = createRawMaterial('Pollo');
    $other = createRawMaterial('Res');
    $chickenBatch = stockBatch($chicken->id, [['purchase_in', '3.000']]);
    stockBatch($other->id, [['purchase_in', '7.000']]);

    $list = (new StockBalance)->activeBatches($chicken->id);

    expect($list->pluck('id')->all())->toBe([$chickenBatch->id]);
});

it('lists active batches with one aggregate query plus one eager load, not one per batch [BR-14]', function () {
    $product = createRawMaterial();
    stockBatch($product->id, [['purchase_in', '1.000']]);
    stockBatch($product->id, [['purchase_in', '2.000']]);

    DB::enableQueryLog();
    (new StockBalance)->activeBatches($product->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // 1 agregado sobre inventory_movements + 1 carga ansiosa de productos, sin importar cuántos lotes haya.
    expect($queries)->toHaveCount(2);
});

it('re-reads the balance inside a transaction after the caller locks the batch [BR-14]', function () {
    $batch = stockBatch(createRawMaterial()->id, [['purchase_in', '10.000']]);

    $balance = DB::transaction(function () use ($batch) {
        Batch::whereKey($batch->id)->lockForUpdate()->first();

        return (new StockBalance)->forBatchLocked($batch->id);
    });

    expect($balance)->toBe('10.000');
});
