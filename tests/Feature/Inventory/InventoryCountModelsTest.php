<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountLine;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// M4 2.3 · BR-16, DEC-05 (1 B), DEC-07, C9: modelos y factories del conteo físico.

it('builds a draft inventory count with fictitious data [BR-16]', function () {
    $count = InventoryCount::factory()->create();

    expect($count->status)->toBe('draft')
        ->and($count->code)->toMatch('/^C-\d{4,}$/')
        ->and($count->createdBy)->toBeInstanceOf(User::class)
        ->and($count->confirmedBy)->toBeNull()
        ->and($count->confirmed_at)->toBeNull()
        ->and($count->lines)->toHaveCount(0);
});

it('offers confirmed and cancelled states [DEC-07]', function () {
    $confirmed = InventoryCount::factory()->confirmed()->create();
    $cancelled = InventoryCount::factory()->cancelled()->create();

    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->confirmedBy)->toBeInstanceOf(User::class)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($cancelled->status)->toBe('cancelled')
        ->and($cancelled->confirmed_at)->toBeNull();
});

it('generates unique codes across counts [BR-16]', function () {
    $codes = InventoryCount::factory()->count(5)->create()->pluck('code');

    expect($codes->unique())->toHaveCount(5);
});

it('casts count line weights to three decimals without float [BR-16, C9]', function () {
    $line = InventoryCountLine::factory()->weighed()->create([
        'system_quantity_kg' => '200',
        'counted_weight_kg' => '201.5',
        'difference_kg' => '1.5',
    ])->fresh();

    expect($line->system_quantity_kg)->toBe('200.000')
        ->and($line->counted_weight_kg)->toBe('201.500')
        ->and($line->difference_kg)->toBe('1.500');
});

it('keeps an unweighed line with null weights [BR-16]', function () {
    $line = InventoryCountLine::factory()->create()->fresh();

    expect($line->system_quantity_kg)->toBeNull()
        ->and($line->counted_weight_kg)->toBeNull()
        ->and($line->difference_kg)->toBeNull()
        ->and($line->responsible)->toBeNull()
        ->and($line->count)->toBeInstanceOf(InventoryCount::class)
        ->and($line->batch)->toBeInstanceOf(Batch::class);
});

it('links a weighed line to who typed it and who is responsible [DEC-05]', function () {
    $line = InventoryCountLine::factory()->weighed()->create();

    expect($line->countedBy)->toBeInstanceOf(User::class)
        ->and($line->responsible)->toBeInstanceOf(User::class)
        ->and($line->counted_at)->not->toBeNull();
});

it('exposes the lines of a count and the count lines unique per batch [BR-16]', function () {
    $count = InventoryCount::factory()->create();
    $batch = Batch::factory()->create();
    InventoryCountLine::factory()->create(['count_id' => $count->id, 'batch_id' => $batch->id]);

    expect($count->lines()->count())->toBe(1);

    expect(fn () => InventoryCountLine::factory()->create(['count_id' => $count->id, 'batch_id' => $batch->id]))
        ->toThrow(QueryException::class);
});

it('links a count line to its adjustment movement [BR-16]', function () {
    $batch = Batch::factory()->create();
    $user = User::factory()->create();
    $movement = InventoryMovement::create([
        'batch_id' => $batch->id, 'type' => 'count_adjustment', 'quantity' => '-1.000',
        'unit_cost' => '1.0000', 'user_id' => $user->id, 'occurred_at' => now(),
    ]);
    $line = InventoryCountLine::factory()->create(['batch_id' => $batch->id, 'adjustment_movement_id' => $movement->id]);

    expect($line->adjustmentMovement->is($movement))->toBeTrue();
});

it('relates a movement to its responsible user and stays immutable [DEC-05, C10]', function () {
    $batch = Batch::factory()->create();
    $registrar = User::factory()->create();
    $responsible = User::factory()->create();

    $movement = InventoryMovement::create([
        'batch_id' => $batch->id, 'type' => 'shrinkage_slicing', 'quantity' => '-1.000',
        'unit_cost' => '1.0000', 'user_id' => $registrar->id, 'responsible_user_id' => $responsible->id,
        'reason' => 'Rebanado de prueba', 'occurred_at' => now(),
    ]);

    expect($movement->responsible->is($responsible))->toBeTrue()
        ->and($movement->user->is($registrar))->toBeTrue();

    expect(fn () => DB::table('inventory_movements')->where('id', $movement->id)->update(['responsible_user_id' => $registrar->id]))
        ->toThrow(QueryException::class, 'es de solo inserción');
});

it('leaves the responsible of old movements empty [DEC-05]', function () {
    $movement = InventoryMovement::create([
        'batch_id' => Batch::factory()->create()->id, 'type' => 'purchase_in', 'quantity' => '1.000',
        'unit_cost' => '1.0000', 'user_id' => User::factory()->create()->id, 'occurred_at' => now(),
    ]);

    expect($movement->responsible)->toBeNull();
});
