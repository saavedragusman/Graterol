<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Queries\StockBalance;
use App\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// C9/BR-14: valor del lote = batches.total_cost + Σ total_cost con signo de los movimientos
// que no son la entrada inicial. NULL cae a round(quantity × unit_cost). Nunca saldo × unit_cost.

function valueBatch(string $totalCost, string $unitCost, array $movements): Batch
{
    $batch = Batch::factory()->create([
        'product_id' => createRawMaterial()->id,
        'total_cost' => $totalCost,
        'unit_cost' => $unitCost,
    ]);
    $userId = User::factory()->create()->id;

    foreach ($movements as [$type, $quantity, $movementTotal]) {
        InventoryMovement::create([
            'batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $movementTotal,
            'user_id' => $userId,
            'occurred_at' => now(),
        ]);
    }

    return $batch;
}

it('adds a nullable numeric(18,2) total_cost column to the kardex [C9]', function () {
    expect(Schema::hasColumn('inventory_movements', 'total_cost'))->toBeTrue();

    $column = DB::selectOne(
        "select is_nullable, numeric_precision, numeric_scale from information_schema.columns where table_name = 'inventory_movements' and column_name = 'total_cost'"
    );

    expect($column->is_nullable)->toBe('YES')
        ->and((int) $column->numeric_precision)->toBe(18)
        ->and((int) $column->numeric_scale)->toBe(2);
});

it('casts total_cost to 2 decimals and keeps NULL as null [C9]', function () {
    $batch = valueBatch('10.00', '1.0000', [
        ['sale_out', '-1.000', '-1'],
        ['sale_out', '-2.000', null],
    ]);

    $rows = InventoryMovement::where('batch_id', $batch->id)->orderBy('id')->get();

    expect($rows[0]->total_cost)->toBe('-1.00')
        ->and($rows[1]->total_cost)->toBeNull();
});

it('values a batch without exits at its total_cost, ignoring the initial entry movement [BR-14][C9]', function () {
    $batch = valueBatch('190.01', '0.9501', [['purchase_in', '200.000', '190.01']]);

    $value = (new StockBalance)->valueForBatch($batch->id);

    expect($value)->toBeInstanceOf(Money::class)
        ->and($value->toInternalString())->toBe('190.01');
});

it('ignores butchery_out as the initial entry of a butchery lot [C9]', function () {
    $batch = valueBatch('50.00', '0.5000', [['butchery_out', '100.000', '50.00'], ['sale_out', '-40.000', '-20.00']]);

    expect((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('30.00');
});

it('subtracts the signed exact cost of every non-initial movement [C9]', function () {
    $batch = valueBatch('902.60', '0.9501', [
        ['purchase_in', '950.000', '902.60'],
        ['sale_out', '-100.000', '-95.01'],
        ['shrinkage_expiry', '-50.000', '-47.51'],
        ['count_adjustment', '1.000', '0.00'],
    ]);

    expect((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('760.08');
});

it('falls back to round(quantity × unit_cost) half-up for movements with NULL total_cost [C9]', function () {
    // 10.500 × 0.3333 = 3.49965 → 3.50; el valor nunca es saldo × unit_cost.
    $batch = valueBatch('100.00', '0.3333', [
        ['purchase_in', '300.000', null],
        ['butchery_in', '-10.500', null],
    ]);

    expect((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('96.50');
});

it('rounds the NULL fallback of a negative exit half-up away from zero [C9]', function () {
    // 1.000 × 0.0050 = 0.005 → 0.01 (en valor absoluto).
    $batch = valueBatch('10.00', '0.0050', [['sale_out', '-1.000', null]]);

    expect((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('9.99');
});

it('handles a batch whose balance went negative without computing balance × unit_cost [BR-17][C9]', function () {
    $batch = valueBatch('10.00', '1.0000', [
        ['purchase_in', '10.000', '10.00'],
        ['sale_out', '-10.000', '-10.00'],
        ['sale_out', '-2.000', '-2.00'],
    ]);

    expect((new StockBalance)->forBatch($batch->id))->toBe('-2.000')
        ->and((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('-2.00');
});

it('values a batch inside a transaction after the caller locks it [C11]', function () {
    $batch = valueBatch('10.00', '1.0000', []);

    $value = DB::transaction(function () use ($batch) {
        Batch::whereKey($batch->id)->lockForUpdate()->first();

        return (new StockBalance)->valueForBatchLocked($batch->id);
    });

    expect($value->toInternalString())->toBe('10.00');
});
