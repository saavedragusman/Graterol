<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreateLotFromButcheryOutputAction;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-12/C10: cada corte con peso real > 0 nace como su propio lote, con el
// costo asignado por AllocateButcheryCostsAction, y su movimiento butchery_out.

it('creates the output batch with unit cost = allocated cost ÷ actual weight [BR-12]', function () {
    $user = User::factory()->create();
    $filete = createCut('Filete');

    $batch = (new CreateLotFromButcheryOutputAction)->execute(
        sourceId: 1,
        productId: $filete->id,
        weightKg: '4.800',
        totalCost: '24.5738',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    expect($batch->product_id)->toBe($filete->id)
        ->and($batch->total_cost)->toBe('24.5738')
        ->and($batch->unit_cost)->toBe('5.1195')
        ->and($batch->code)->toBe('butchery_order_line-1');
});

it('registers the butchery_out movement linked to the order line [BR-12][C10]', function () {
    $user = User::factory()->create();
    $filete = createCut('Filete');

    $batch = (new CreateLotFromButcheryOutputAction)->execute(
        sourceId: 7,
        productId: $filete->id,
        weightKg: '6.400',
        totalCost: '19.1550',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $movement = InventoryMovement::where('batch_id', $batch->id)->first();

    expect($movement->type)->toBe('butchery_out')
        ->and($movement->quantity)->toBe('6.400')
        ->and($movement->reference_type)->toBe('butchery_order_line')
        ->and($movement->reference_id)->toBe(7);
});

it('rejects creating an output batch with zero or negative weight [BR-12]', function () {
    $user = User::factory()->create();
    $filete = createCut('Filete');

    $create = fn () => (new CreateLotFromButcheryOutputAction)->execute(
        sourceId: 4,
        productId: $filete->id,
        weightKg: '0.000',
        totalCost: '0.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    expect($create)->toThrow(InvalidArgumentException::class);
});

it('rejects creating two output batches from the same order line [C10]', function () {
    $user = User::factory()->create();
    $filete = createCut('Filete');

    $create = fn () => (new CreateLotFromButcheryOutputAction)->execute(
        sourceId: 9,
        productId: $filete->id,
        weightKg: '1.000',
        totalCost: '5.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $create();

    expect($create)->toThrow(QueryException::class);
});
