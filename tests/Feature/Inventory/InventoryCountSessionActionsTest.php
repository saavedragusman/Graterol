<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CancelInventoryCountAction;
use App\Domain\Inventory\Actions\OpenInventoryCountAction;
use App\Domain\Inventory\Actions\RegisterCountedWeightAction;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountLine;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-16/DEC-07: abrir, pesar y descartar un conteo físico.

function countLot(string $kg, string $totalCost = '20.00', string $unitCost = '2.0000'): Batch
{
    $batch = Batch::factory()->create([
        'product_id' => createRawMaterial()->id,
        'total_cost' => $totalCost,
        'unit_cost' => $unitCost,
    ]);

    InventoryMovement::create([
        'batch_id' => $batch->id,
        'type' => MovementType::PurchaseIn->value,
        'quantity' => $kg,
        'unit_cost' => $unitCost,
        'total_cost' => $totalCost,
        'user_id' => User::factory()->create()->id,
        'occurred_at' => now(),
    ]);

    return $batch;
}

function sellFromLot(Batch $batch, string $kg): void
{
    InventoryMovement::create([
        'batch_id' => $batch->id,
        'type' => MovementType::SaleOut->value,
        'quantity' => '-'.$kg,
        'unit_cost' => $batch->unit_cost,
        'total_cost' => '0.00',
        'user_id' => User::factory()->create()->id,
        'occurred_at' => now(),
    ]);
}

function openCount(array $batchIds, ?User $user = null, ?string $notes = null): InventoryCount
{
    return (new OpenInventoryCountAction)->execute($batchIds, ($user ?? User::factory()->create())->id, $notes);
}

function weigh(InventoryCountLine $line, string $kg, ?int $responsibleId, ?User $typist = null): InventoryCountLine
{
    return (new RegisterCountedWeightAction)->execute($line->id, $kg, $responsibleId, ($typist ?? User::factory()->create())->id);
}

function countSnapshot(): array
{
    return [InventoryMovement::count(), AuditLog::count(), InventoryCount::query()->orderBy('id')->get(['id', 'status', 'confirmed_by'])->toArray(),
        InventoryCountLine::query()->orderBy('id')->get()->toArray()];
}

it('opens a draft session with one line per chosen batch [BR-16]', function () {
    $a = countLot('10.000');
    $b = countLot('5.000');
    countLot('7.000');
    $user = User::factory()->create();

    $count = openCount([$b->id, $a->id], $user, 'conteo de cierre');

    expect($count->status)->toBe('draft')
        ->and($count->code)->toBe('C-0001')
        ->and($count->created_by)->toBe($user->id)
        ->and($count->notes)->toBe('conteo de cierre')
        ->and($count->confirmed_by)->toBeNull()
        ->and($count->confirmed_at)->toBeNull();

    $lines = InventoryCountLine::where('count_id', $count->id)->orderBy('batch_id')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines->pluck('batch_id')->all())->toBe([$a->id, $b->id])
        ->and($lines->every(fn ($l) => $l->counted_weight_kg === null && $l->system_quantity_kg === null
            && $l->difference_kg === null && $l->responsible_user_id === null && $l->adjustment_movement_id === null))->toBeTrue();
});

it('opens by default with every batch that has a positive balance [BR-16]', function () {
    $a = countLot('10.000');
    $empty = countLot('4.000');
    sellFromLot($empty, '4.000');
    $c = countLot('1.000');

    $count = openCount([]);

    expect(InventoryCountLine::where('count_id', $count->id)->orderBy('batch_id')->pluck('batch_id')->all())
        ->toBe([$a->id, $c->id]);
});

it('rejects an empty selection and unknown batches, leaving nothing behind [BR-16]', function () {
    expect(fn () => openCount([]))->toThrow(InvalidArgumentException::class);
    expect(InventoryCount::count())->toBe(0);

    $a = countLot('10.000');

    expect(fn () => openCount([$a->id, 999999]))->toThrow(InvalidArgumentException::class);
    expect(InventoryCount::count())->toBe(0)->and(InventoryCountLine::query()->count())->toBe(0);
});

it('rejects duplicated batch ids as one line each [BR-16]', function () {
    $a = countLot('10.000');

    $count = openCount([$a->id, $a->id]);

    expect(InventoryCountLine::where('count_id', $count->id)->count())->toBe(1);
});

it('generates unique sequential zero padded codes [BR-16]', function () {
    $a = countLot('10.000');

    $codes = [openCount([$a->id])->code, openCount([$a->id])->code, openCount([$a->id])->code];

    expect($codes)->toBe(['C-0001', 'C-0002', 'C-0003']);
});

it('weighs a line computing the difference from the locked balance [BR-16][DEC-07]', function () {
    $batch = countLot('10.000');
    sellFromLot($batch, '1.500');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    $typist = User::factory()->create();
    $responsible = User::factory()->create();

    $result = weigh($line, '7.250', $responsible->id, $typist);

    $line->refresh();
    expect($result->id)->toBe($line->id)
        ->and((string) $line->system_quantity_kg)->toBe('8.500')
        ->and((string) $line->counted_weight_kg)->toBe('7.250')
        ->and((string) $line->difference_kg)->toBe('-1.250')
        ->and($line->counted_by)->toBe($typist->id)
        ->and($line->responsible_user_id)->toBe($responsible->id)
        ->and($line->counted_at)->not->toBeNull()
        ->and($line->adjustment_movement_id)->toBeNull()
        ->and(InventoryMovement::count())->toBe(2)
        ->and(AuditLog::count())->toBe(0)
        ->and($count->fresh()->status)->toBe('draft');
});

it('allows zero and surplus weights [BR-16]', function () {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    $user = User::factory()->create();

    weigh($line, '0', $user->id);
    expect((string) $line->fresh()->counted_weight_kg)->toBe('0.000')
        ->and((string) $line->fresh()->difference_kg)->toBe('-10.000');

    weigh($line, '10.500', $user->id);
    expect((string) $line->fresh()->difference_kg)->toBe('0.500');
});

it('overwrites a previous weight while the session is draft [BR-16]', function () {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $typist2 = User::factory()->create();

    weigh($line, '9.000', $first->id);
    sellFromLot($batch, '2.000');
    weigh($line, '7.000', $second->id, $typist2);

    $line->refresh();
    expect((string) $line->system_quantity_kg)->toBe('8.000')
        ->and((string) $line->counted_weight_kg)->toBe('7.000')
        ->and((string) $line->difference_kg)->toBe('-1.000')
        ->and($line->responsible_user_id)->toBe($second->id)
        ->and($line->counted_by)->toBe($typist2->id)
        ->and(InventoryCountLine::query()->count())->toBe(1);
});

it('requires an existing active responsible and a valid weight [DEC-05]', function (string $kg, string $who) {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    $inactive = User::factory()->create(['is_active' => false]);
    $responsible = match ($who) {
        'null' => null,
        'missing' => 999999,
        'inactive' => $inactive->id,
        'ok' => User::factory()->create()->id,
    };
    $before = countSnapshot();

    expect(fn () => weigh($line, $kg, $responsible))->toThrow(InvalidArgumentException::class);
    expect(countSnapshot())->toBe($before);
})->with([
    'no responsible' => ['5.000', 'null'],
    'unknown responsible' => ['5.000', 'missing'],
    'inactive responsible' => ['5.000', 'inactive'],
    'negative weight' => ['-1.000', 'ok'],
    'four decimals' => ['1.2345', 'ok'],
    'not a number' => ['abc', 'ok'],
]);

it('rejects weighing when the session is not draft [C12]', function (string $state) {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $line = $count->lines()->sole();
    $count->update(['status' => $state]);
    $before = countSnapshot();

    expect(fn () => weigh($line, '5.000', User::factory()->create()->id))->toThrow(RuntimeException::class);
    expect(countSnapshot())->toBe($before);
})->with(['confirmed', 'cancelled']);

it('cancels a draft session without writing movements [C12]', function () {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    weigh($count->lines()->sole(), '5.000', User::factory()->create()->id);
    $kardex = InventoryMovement::count();

    $result = (new CancelInventoryCountAction)->execute($count->id);

    expect($result->status)->toBe('cancelled')
        ->and($count->fresh()->status)->toBe('cancelled')
        ->and($count->fresh()->confirmed_by)->toBeNull()
        ->and(InventoryMovement::count())->toBe($kardex)
        ->and(AuditLog::count())->toBe(0);
});

it('rejects cancelling a session that is not draft [C12]', function (string $state) {
    $batch = countLot('10.000');
    $count = openCount([$batch->id]);
    $count->update(['status' => $state]);
    $before = countSnapshot();

    expect(fn () => (new CancelInventoryCountAction)->execute($count->id))->toThrow(RuntimeException::class);
    expect(countSnapshot())->toBe($before);
})->with(['confirmed', 'cancelled']);
