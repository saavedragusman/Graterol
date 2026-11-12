<?php

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeInventoryMovement(): InventoryMovement
{
    $batch = Batch::create([
        'code' => 'L-0001',
        'source_type' => 'purchase',
        'unit' => 'kg',
        'unit_cost' => '120.0000',
        'received_at' => now(),
    ]);

    $user = User::create([
        'name' => 'Carnicero de prueba',
        'email' => 'carnicero@example.test',
        'password' => bcrypt('password'),
    ]);

    return InventoryMovement::create([
        'batch_id' => $batch->id,
        'type' => 'purchase_in',
        'quantity' => '20.000',
        'unit_cost' => '120.0000',
        'user_id' => $user->id,
        'occurred_at' => now(),
    ]);
}

// C10: inventory_movements es de solo inserción; el trigger rechaza UPDATE y DELETE.
it('rejects UPDATE on inventory_movements [C10]', function () {
    $movement = makeInventoryMovement();

    expect(fn () => DB::table('inventory_movements')
        ->where('id', $movement->id)
        ->update(['quantity' => '999.000'])
    )->toThrow(QueryException::class);
});

it('rejects DELETE on inventory_movements [C10]', function () {
    $movement = makeInventoryMovement();

    expect(fn () => DB::table('inventory_movements')
        ->where('id', $movement->id)
        ->delete()
    )->toThrow(QueryException::class);
});
