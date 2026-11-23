<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CancelInventoryCountAction;
use App\Domain\Inventory\Actions\ConfirmInventoryCountAction;
use App\Domain\Inventory\Actions\RegisterCountedWeightAction;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-16/BR-15/C15/DEC-11 B: confirmar el conteo genera los ajustes. Usa los helpers de
// InventoryCountSessionActionsTest (countLot, openCount, weigh, sellFromLot, countSnapshot).

function confirmCount(InventoryCount $count, ?User $user = null): InventoryCount
{
    return (new ConfirmInventoryCountAction)->execute($count->id, ($user ?? User::factory()->create())->id);
}

it('writes a negative adjustment costed at kg x unit cost with audit [BR-16][C15]', function () {
    $batch = countLot('10.000', '20.00', '2.0000');
    $count = openCount([$batch->id]);
    $responsible = User::factory()->create();
    weigh($count->lines()->sole(), '8.500', $responsible->id);
    $confirmer = User::factory()->create();

    $result = confirmCount($count, $confirmer);

    $movement = InventoryMovement::where('type', MovementType::CountAdjustment->value)->sole();
    $line = $count->lines()->sole();
    expect($result->status)->toBe('confirmed')
        ->and($result->confirmed_by)->toBe($confirmer->id)
        ->and($result->confirmed_at)->not->toBeNull()
        ->and($batch->id)->toBe($movement->batch_id)
        ->and((string) $movement->quantity)->toBe('-1.500')
        ->and((string) $movement->unit_cost)->toBe('2.0000')
        ->and((string) $movement->total_cost)->toBe('-3.00')
        ->and($movement->user_id)->toBe($confirmer->id)
        ->and($movement->responsible_user_id)->toBe($responsible->id)
        ->and($movement->reference_type)->toBe('inventory_count')
        ->and($movement->reference_id)->toBe($count->id)
        ->and($movement->reason)->toContain($count->code)
        ->and($line->adjustment_movement_id)->toBe($movement->id)
        ->and((new StockBalance)->forBatch($batch->id))->toBe('8.500')
        ->and((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('17.00');

    $audit = AuditLog::sole();
    expect($audit->auditable_type)->toBe('batch')
        ->and($audit->auditable_id)->toBe($batch->id)
        ->and($audit->action)->toBe('inventory.count_adjusted')
        ->and($audit->user_id)->toBe($confirmer->id)
        ->and($audit->reason)->toContain($count->code)
        ->and($audit->before)->toBe(['balance_kg' => '10.000', 'value' => '20.00', 'type' => 'count_adjustment', 'responsible_user_id' => $responsible->id])
        ->and($audit->after)->toBe(['balance_kg' => '8.500', 'value' => '17.00', 'type' => 'count_adjustment', 'responsible_user_id' => $responsible->id]);
});

it('enters a surplus at zero cost without adding value [DEC-11 B]', function () {
    $batch = countLot('200.000', '190.01', '0.9501');
    $count = openCount([$batch->id]);
    $responsible = User::factory()->create();
    weigh($count->lines()->sole(), '201.000', $responsible->id);
    $confirmer = User::factory()->create();

    confirmCount($count, $confirmer);

    $movement = InventoryMovement::where('type', MovementType::CountAdjustment->value)->sole();
    expect((string) $movement->quantity)->toBe('1.000')
        ->and((string) $movement->unit_cost)->toBe('0.9501')
        ->and((string) $movement->total_cost)->toBe('0.00')
        ->and($movement->user_id)->toBe($confirmer->id)
        ->and($movement->responsible_user_id)->toBe($responsible->id)
        ->and($movement->reference_type)->toBe('inventory_count')
        ->and($movement->reference_id)->toBe($count->id)
        ->and((new StockBalance)->forBatch($batch->id))->toBe('201.000')
        ->and((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('190.01');

    $audit = AuditLog::sole();
    expect($audit->before['balance_kg'])->toBe('200.000')
        ->and($audit->before['value'])->toBe('190.01')
        ->and($audit->after['balance_kg'])->toBe('201.000')
        ->and($audit->after['value'])->toBe('190.01');
});

it('takes the exact residue when the count leaves the lot at zero [C9]', function () {
    $batch = countLot('200.000', '190.01', '0.9501');
    $count = openCount([$batch->id]);
    weigh($count->lines()->sole(), '0', User::factory()->create()->id);

    confirmCount($count);

    $movement = InventoryMovement::where('type', MovementType::CountAdjustment->value)->sole();
    expect((string) $movement->quantity)->toBe('-200.000')
        ->and((string) $movement->total_cost)->toBe('-190.01')
        ->and((new StockBalance)->forBatch($batch->id))->toBe('0.000')
        ->and((new StockBalance)->valueForBatch($batch->id)->toInternalString())->toBe('0.00')
        ->and(AuditLog::sole()->after['value'])->toBe('0.00');
});

it('writes no movement or audit for lines with zero difference [BR-16]', function () {
    $same = countLot('10.000');
    $off = countLot('5.000');
    $count = openCount([$same->id, $off->id]);
    $user = User::factory()->create();
    foreach ($count->lines()->orderBy('batch_id')->get() as $line) {
        weigh($line, $line->batch_id === $same->id ? '10.000' : '4.000', $user->id);
    }
    $kardex = InventoryMovement::count();

    confirmCount($count);

    expect(InventoryMovement::count())->toBe($kardex + 1)
        ->and(AuditLog::count())->toBe(1)
        ->and($count->lines()->where('batch_id', $same->id)->sole()->adjustment_movement_id)->toBeNull()
        ->and($count->lines()->where('batch_id', $off->id)->sole()->adjustment_movement_id)->not->toBeNull()
        ->and($count->fresh()->status)->toBe('confirmed');
});

it('confirms a session with nothing to adjust [BR-16]', function () {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    weigh($count->lines()->sole(), '10.000', User::factory()->create()->id);
    $kardex = InventoryMovement::count();

    confirmCount($count);

    expect(InventoryMovement::count())->toBe($kardex)->and(AuditLog::count())->toBe(0)
        ->and($count->fresh()->status)->toBe('confirmed');
});

it('adjusts every line of a session each with its own responsible [DEC-05]', function () {
    $a = countLot('10.000');
    $b = countLot('10.000');
    $count = openCount([$a->id, $b->id]);
    $ra = User::factory()->create();
    $rb = User::factory()->create();
    $lines = $count->lines()->orderBy('batch_id')->get();
    weigh($lines[0], '9.000', $ra->id);
    weigh($lines[1], '11.000', $rb->id);

    confirmCount($count);

    expect(InventoryMovement::where('batch_id', $a->id)->where('type', 'count_adjustment')->sole()->responsible_user_id)->toBe($ra->id)
        ->and(InventoryMovement::where('batch_id', $b->id)->where('type', 'count_adjustment')->sole()->responsible_user_id)->toBe($rb->id)
        ->and(AuditLog::count())->toBe(2);
});

it('rejects an incomplete session and writes nothing [BR-16]', function () {
    $a = countLot('10.000');
    $b = countLot('10.000');
    $count = openCount([$a->id, $b->id]);
    weigh($count->lines()->orderBy('batch_id')->first(), '5.000', User::factory()->create()->id);
    $before = countSnapshot();

    expect(fn () => confirmCount($count))->toThrow(RuntimeException::class, 'sin pesar');
    expect(countSnapshot())->toBe($before);
});

it('rejects confirming a cancelled or already confirmed session [C12]', function () {
    $batch = countLot('10.000');
    $cancelled = openCount([$batch->id]);
    weigh($cancelled->lines()->sole(), '5.000', User::factory()->create()->id);
    (new CancelInventoryCountAction)->execute($cancelled->id);

    $confirmed = openCount([$batch->id]);
    weigh($confirmed->lines()->sole(), '9.000', User::factory()->create()->id);
    confirmCount($confirmed);
    $before = countSnapshot();

    expect(fn () => confirmCount($cancelled))->toThrow(RuntimeException::class);
    expect(fn () => confirmCount($confirmed))->toThrow(RuntimeException::class);
    expect(countSnapshot())->toBe($before);
});

it('does not allow weighing or cancelling a confirmed session [C12]', function () {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    weigh($line, '9.000', User::factory()->create()->id);
    confirmCount($count);
    $before = countSnapshot();

    expect(fn () => (new RegisterCountedWeightAction)->execute($line->id, '1.000', User::factory()->create()->id, User::factory()->create()->id))
        ->toThrow(RuntimeException::class);
    expect(fn () => (new CancelInventoryCountAction)->execute($count->id))->toThrow(RuntimeException::class);
    expect(countSnapshot())->toBe($before);
});

it('rejects the whole confirmation if a negative adjustment would leave a negative balance [DEC-07]', function () {
    $a = countLot('10.000');
    $b = countLot('10.000');
    $count = openCount([$a->id, $b->id]);
    $user = User::factory()->create();
    $lines = $count->lines()->orderBy('batch_id')->get();
    weigh($lines[0], '9.000', $user->id);   // -1.000 sobre A: válido
    weigh($lines[1], '2.000', $user->id);   // -8.000 sobre B
    sellFromLot($b, '5.000');               // B queda con 5.000 < 8.000
    $before = countSnapshot();

    expect(fn () => confirmCount($count))->toThrow(RuntimeException::class, 'saldo negativo');
    expect(countSnapshot())->toBe($before)
        ->and((new StockBalance)->forBatch($a->id))->toBe('10.000');
});

it('applies the stored difference even if the balance moved but stays non negative [DEC-07]', function () {
    $batch = countLot('10.000', '20.00', '2.0000');
    $count = openCount([$batch->id]);
    weigh($count->lines()->sole(), '8.000', User::factory()->create()->id); // -2.000
    sellFromLot($batch, '3.000');

    confirmCount($count);

    $movement = InventoryMovement::where('type', 'count_adjustment')->sole();
    expect((string) $movement->quantity)->toBe('-2.000')
        ->and((new StockBalance)->forBatch($batch->id))->toBe('5.000');
});
