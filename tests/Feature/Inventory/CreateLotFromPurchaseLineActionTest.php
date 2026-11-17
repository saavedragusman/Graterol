<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-07/C10: el lote nace en la recepción con su costo total; el movimiento
// de entrada queda en el kardex de solo inserción.

it('creates the batch with unit cost = total cost ÷ received weight [BR-07]', function () {
    $user = User::factory()->create();
    $product = createRawMaterial();

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 1,
        productId: $product->id,
        pieces: null,
        receivedWeightKg: '98.000',
        totalCost: '110.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    expect($batch->unit)->toBe('kg')
        ->and($batch->total_cost)->toBe('110.0000')
        ->and($batch->unit_cost)->toBe('1.1224')
        ->and($batch->currency)->toBe('VES')
        ->and($batch->exchange_rate)->toBe('85.5000')
        ->and($batch->code)->toBe('purchase_line-1')
        ->and($batch->product_id)->toBe($product->id);
});

it('registers the entry movement in the kardex, linked to the purchase line [BR-07][C10]', function () {
    $user = User::factory()->create();
    $product = createRawMaterial();

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 7,
        productId: $product->id,
        pieces: 2,
        receivedWeightKg: '45.750',
        totalCost: '150.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $movement = InventoryMovement::where('batch_id', $batch->id)->first();

    expect($movement->type)->toBe('purchase_in')
        ->and($movement->quantity)->toBe('45.750')
        ->and($movement->reference_type)->toBe('purchase_line')
        ->and($movement->reference_id)->toBe(7)
        ->and($movement->user_id)->toBe($user->id);
});

it('saves the piece count when the purchase was by piece [BR-07]', function () {
    $user = User::factory()->create();
    $product = createRawMaterial();

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 2,
        productId: $product->id,
        pieces: 2,
        receivedWeightKg: '83.400',
        totalCost: '300.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    expect($batch->pieces)->toBe(2);
});

it('rejects creating two lots from the same source [C10]', function () {
    $user = User::factory()->create();
    $product = createRawMaterial();

    $create = fn () => (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 9,
        productId: $product->id,
        pieces: null,
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $create();

    expect($create)->toThrow(QueryException::class);
});
