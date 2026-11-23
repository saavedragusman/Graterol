<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RegisterShrinkageAction;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-15: merma tipificada de un lote, con motivo y responsable. C15: auditoría. C9: costo exacto.

function shrinkLot(string $kg, string $totalCost, string $unitCost, ?string $expiresAt = null): Batch
{
    $batch = Batch::factory()->create([
        'product_id' => createRawMaterial()->id,
        'total_cost' => $totalCost,
        'unit_cost' => $unitCost,
        'expires_at' => $expiresAt,
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

function shrink(Batch $batch, MovementType $type, string $kg, ?string $reason, ?int $responsibleId, ?int $userId = null): InventoryMovement
{
    return (new RegisterShrinkageAction)->execute(
        batchId: $batch->id,
        type: $type,
        kg: $kg,
        reason: $reason,
        responsibleUserId: $responsibleId,
        userId: $userId ?? User::factory()->create()->id,
    );
}

function kardexAndAuditCounts(): array
{
    return [InventoryMovement::count(), AuditLog::count()];
}

it('writes the exact movement and audit row for each shrinkage type [BR-15][C15]', function (MovementType $type) {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $registrar = User::factory()->create();
    $responsible = User::factory()->create();

    $movement = shrink($batch, $type, '2.500', '  se secó  ', $responsible->id, $registrar->id);

    $row = InventoryMovement::where('type', $type->value)->sole();
    expect($row->id)->toBe($movement->id)
        ->and($row->batch_id)->toBe($batch->id)
        ->and((string) $row->quantity)->toBe('-2.500')
        ->and((string) $row->unit_cost)->toBe('2.0000')
        ->and((string) $row->total_cost)->toBe('-5.00')
        ->and($row->user_id)->toBe($registrar->id)
        ->and($row->responsible_user_id)->toBe($responsible->id)
        ->and($row->reference_type)->toBeNull()
        ->and($row->reference_id)->toBeNull()
        ->and($row->reason)->toBe('se secó');

    $stock = new StockBalance;
    expect($stock->forBatch($batch->id))->toBe('7.500')
        ->and($stock->valueForBatch($batch->id)->toInternalString())->toBe('15.00');

    $audit = AuditLog::sole();
    expect($audit->auditable_type)->toBe('batch')
        ->and($audit->auditable_id)->toBe($batch->id)
        ->and($audit->action)->toBe('inventory.shrinkage_registered')
        ->and($audit->user_id)->toBe($registrar->id)
        ->and($audit->reason)->toBe('se secó')
        ->and($audit->before)->toBe([
            'balance_kg' => '10.000',
            'value' => '20.00',
            'type' => $type->value,
            'responsible_user_id' => $responsible->id,
        ])
        ->and($audit->after)->toBe([
            'balance_kg' => '7.500',
            'value' => '15.00',
            'type' => $type->value,
            'responsible_user_id' => $responsible->id,
        ]);
})->with([
    MovementType::ShrinkageDehydration,
    MovementType::ShrinkageSlicing,
    MovementType::ShrinkageExpiry,
    MovementType::ShrinkageConfiscation,
]);

it('rejects every non shrinkage type, including count_adjustment [BR-15]', function (MovementType $type) {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $before = kardexAndAuditCounts();

    expect(fn () => shrink($batch, $type, '1.000', 'x', User::factory()->create()->id))
        ->toThrow(InvalidArgumentException::class);

    expect(kardexAndAuditCounts())->toBe($before);
})->with([
    MovementType::CountAdjustment,
    MovementType::SaleOut,
    MovementType::PurchaseIn,
    MovementType::ButcheryOut,
]);

it('requires a non blank reason [BR-15]', function (?string $reason) {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $before = kardexAndAuditCounts();

    expect(fn () => shrink($batch, MovementType::ShrinkageSlicing, '1.000', $reason, User::factory()->create()->id))
        ->toThrow(InvalidArgumentException::class, 'El motivo de la merma es obligatorio.');

    expect(kardexAndAuditCounts())->toBe($before);
})->with([null, '', '   ']);

it('rejects invalid weights [BR-15][BR-01]', function (string $kg) {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $before = kardexAndAuditCounts();

    expect(fn () => shrink($batch, MovementType::ShrinkageSlicing, $kg, 'x', User::factory()->create()->id))
        ->toThrow(InvalidArgumentException::class);

    expect(kardexAndAuditCounts())->toBe($before);
})->with(['0.000', '0', '-1.000', '1.0001']);

it('rejects a weight above the balance and never leaves a negative balance [BR-15]', function () {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $before = kardexAndAuditCounts();

    expect(fn () => shrink($batch, MovementType::ShrinkageExpiry, '10.001', 'x', User::factory()->create()->id))
        ->toThrow(RuntimeException::class, 'La merma (10.001 kg) supera el saldo del lote (10.000 kg).');

    expect(kardexAndAuditCounts())->toBe($before)
        ->and((new StockBalance)->forBatch($batch->id))->toBe('10.000');
});

it('rejects an empty or negative balance batch [BR-15]', function () {
    $empty = shrinkLot('5.000', '10.00', '2.0000');
    shrink($empty, MovementType::ShrinkageSlicing, '5.000', 'todo', User::factory()->create()->id);
    $negative = shrinkLot('1.000', '2.00', '2.0000');
    InventoryMovement::create([
        'batch_id' => $negative->id, 'type' => MovementType::SaleOut->value, 'quantity' => '-3.000',
        'unit_cost' => '2.0000', 'total_cost' => '-6.00', 'user_id' => User::factory()->create()->id, 'occurred_at' => now(),
    ]);
    $before = kardexAndAuditCounts();

    foreach ([$empty, $negative] as $batch) {
        expect(fn () => shrink($batch, MovementType::ShrinkageSlicing, '0.001', 'x', User::factory()->create()->id))
            ->toThrow(RuntimeException::class, 'El lote no tiene saldo para dar de baja.');
    }

    expect(kardexAndAuditCounts())->toBe($before);
});

it('rejects a missing, nonexistent or inactive responsible [BR-15][DEC-05]', function (string $case) {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $before = kardexAndAuditCounts();
    $responsibleId = match ($case) {
        'missing' => null,
        'nonexistent' => 999999,
        'inactive' => User::factory()->inactive()->create()->id,
    };

    expect(fn () => shrink($batch, MovementType::ShrinkageDehydration, '1.000', 'x', $responsibleId))
        ->toThrow(InvalidArgumentException::class, 'El responsable debe ser un usuario existente y activo.');

    expect(kardexAndAuditCounts())->toBe($before);
})->with(['missing', 'nonexistent', 'inactive']);

it('stores a responsible different from the registrar in both columns [BR-15][DEC-05]', function () {
    $batch = shrinkLot('10.000', '20.00', '2.0000');
    $registrar = User::factory()->create();
    $responsible = User::factory()->create();

    shrink($batch, MovementType::ShrinkageConfiscation, '1.000', 'decomiso', $responsible->id, $registrar->id);

    $row = InventoryMovement::where('type', 'shrinkage_confiscation')->sole();
    expect($row->user_id)->toBe($registrar->id)
        ->and($row->responsible_user_id)->toBe($responsible->id);
});

it('writes off an expired lot without applying the FEFO expiry filter [BR-15]', function () {
    $batch = shrinkLot('4.000', '8.00', '2.0000', now()->subDays(10)->startOfDay()->toDateTimeString());
    $responsible = User::factory()->create();

    shrink($batch, MovementType::ShrinkageExpiry, '4.000', 'vencido', $responsible->id);

    $stock = new StockBalance;
    expect($stock->forBatch($batch->id))->toBe('0.000')
        ->and($stock->valueForBatch($batch->id)->toInternalString())->toBe('0.00')
        ->and(InventoryMovement::where('type', 'shrinkage_expiry')->sole()->total_cost)->toBe('-8.00');
});

it('sums exactly the lot cost when written off in two parts [C9][BR-15]', function () {
    $batch = shrinkLot('200.000', '190.01', '0.9501');
    $responsible = User::factory()->create();

    shrink($batch, MovementType::ShrinkageDehydration, '120.000', 'primera', $responsible->id);
    $first = InventoryMovement::where('type', 'shrinkage_dehydration')->sole();
    // 120 × 0.9501 = 114.012 → 114.01
    expect((string) $first->total_cost)->toBe('-114.01');

    shrink($batch, MovementType::ShrinkageDehydration, '80.000', 'segunda', $responsible->id);

    $costs = InventoryMovement::where('type', 'shrinkage_dehydration')->orderBy('id')->pluck('total_cost')->map(fn ($c) => (string) $c)->all();
    // La parte que vacía el lote toma el residuo: 190.01 − 114.01 = 76.00 (y no 80 × 0.9501 = 76.01).
    expect($costs)->toBe(['-114.01', '-76.00']);

    $stock = new StockBalance;
    expect($stock->forBatch($batch->id))->toBe('0.000')
        ->and($stock->valueForBatch($batch->id)->toInternalString())->toBe('0.00');

    $last = AuditLog::orderByDesc('id')->first();
    expect($last->before['value'])->toBe('76.00')
        ->and($last->after['value'])->toBe('0.00')
        ->and($last->after['balance_kg'])->toBe('0.000');
});
