<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreateLotFromButcheryOutputAction;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Inventory\BatchCodeGenerator;
use App\Domain\Inventory\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// BR-14/DEC-10 B: código de lote legible L-####, correlativo, sin huecos.

function insertBatchWithCode(string $code): void
{
    Batch::create(['code' => $code, 'source_type' => 'purchase_line', 'unit' => 'kg', 'unit_cost' => '1.0000', 'received_at' => now()]);
}

it('starts at L-0001 when no batch has a readable code yet [BR-14]', function () {
    $code = DB::transaction(fn () => (new BatchCodeGenerator)->next());

    expect($code)->toBe('L-0001');
});

it('continues after the highest numeric part and ignores codes that do not match L-digits [BR-14]', function () {
    insertBatchWithCode('purchase_line-900');
    insertBatchWithCode('L-0007');
    insertBatchWithCode('L-0003');
    insertBatchWithCode('L-ABC');
    insertBatchWithCode('LX-0999');

    expect(DB::transaction(fn () => (new BatchCodeGenerator)->next()))->toBe('L-0008');
});

it('pads to four digits and lets longer numbers grow [BR-14]', function () {
    insertBatchWithCode('L-9999');

    expect(DB::transaction(fn () => (new BatchCodeGenerator)->next()))->toBe('L-10000');
});

it('leaves no gap when the creation fails and rolls back [BR-14]', function () {
    insertBatchWithCode('L-0001');

    try {
        DB::transaction(function () {
            $code = (new BatchCodeGenerator)->next();
            Batch::create(['code' => $code, 'source_type' => 'purchase_line', 'unit' => 'kg', 'unit_cost' => '1.0000', 'received_at' => now()]);

            throw new RuntimeException('falla simulada');
        });
    } catch (RuntimeException) {
    }

    expect(Batch::where('code', 'L-0002')->exists())->toBeFalse()
        ->and(DB::transaction(fn () => (new BatchCodeGenerator)->next()))->toBe('L-0002');
});

it('gives both lot-creation actions consecutive readable codes [BR-14]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $purchaseLot = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line', sourceId: 12, productId: $chicken->id, pieces: null,
        receivedWeightKg: '20.000', totalCost: '56.00', currency: 'VES', exchangeRate: '85.5000', userId: $user->id,
    );
    $outputLot = (new CreateLotFromButcheryOutputAction)->execute(
        sourceId: 5, productId: $filete->id, weightKg: '4.800', totalCost: '24.57',
        currency: 'VES', exchangeRate: '85.5000', userId: $user->id,
    );

    expect($purchaseLot->code)->toBe('L-0001')
        ->and($outputLot->code)->toBe('L-0002')
        ->and($purchaseLot->source_id)->toBe(12)
        ->and($outputLot->source_type)->toBe('butchery_order_line');
});
