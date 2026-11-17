<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\ReceivePurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-06/BR-07: toda recepción se pesa; el lote nace con compra + flete + otros costos.

function receivePurchaseFixtures(): array
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create();

    return [$supplier, $user];
}

it('weighs the single line and records the difference against the invoice [BR-06]', function () {
    [$supplier, $user] = receivePurchaseFixtures();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-200',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
        freightAmount: '0.0000',
    );
    $line = $purchase->lines->first();

    $received = (new ReceivePurchaseAction)->execute($purchase, [
        $line->id => ['received_weight_kg' => '98.000'],
    ], $user->id);

    $receivedLine = $received->lines->first();

    expect($received->status)->toBe('received')
        ->and($received->received_by)->toBe($user->id)
        ->and($receivedLine->received_weight_kg)->toBe('98.000')
        ->and($receivedLine->weight_difference_kg)->toBe('-2.000');
});

it('the lot cost equals the invoiced amount plus the allocated freight, divided by received weight [BR-07]', function () {
    [$supplier, $user] = receivePurchaseFixtures();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-201',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
        freightAmount: '15.0000',
    );
    $line = $purchase->lines->first();

    $received = (new ReceivePurchaseAction)->execute($purchase, [
        $line->id => ['received_weight_kg' => '100.000'],
    ], $user->id);

    $batch = $received->lines->first()->batch;

    expect($batch->total_cost)->toBe('125.0000')
        ->and($batch->unit_cost)->toBe('1.2500');
});

it('allocates freight proportionally to weight and creates one lot per line [BR-07][DEC-02]', function () {
    [$supplier, $user] = receivePurchaseFixtures();
    $chicken = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
    $pork = (new CreateProductAction)->execute('Media res de cochino', ProductType::RawMaterial, Unit::Piece, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-202',
        invoiceDate: now(),
        lines: [
            ['product_id' => $chicken->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
            ['product_id' => $pork->id, 'invoiced_weight_kg' => '45.000', 'invoiced_pieces' => 2, 'line_amount' => '150.0000'],
        ],
        freightAmount: '15.0000',
    );
    $chickenLine = $purchase->lines[0];
    $porkLine = $purchase->lines[1];

    $received = (new ReceivePurchaseAction)->execute($purchase, [
        $chickenLine->id => ['received_weight_kg' => '101.200'],
        $porkLine->id => ['received_weight_kg' => '45.750', 'received_pieces' => 2],
    ], $user->id);

    $batches = $received->lines->pluck('batch');

    expect($batches)->toHaveCount(2)
        ->and($batches[0]->id)->not->toBe($batches[1]->id)
        ->and(bcadd($received->lines[0]->allocated_freight, $received->lines[1]->allocated_freight, 4))->toBe('15.0000')
        ->and($received->lines[1]->batch->pieces)->toBe(2);
});

it('rejects receiving when a line has no weighed value [BR-06]', function () {
    [$supplier, $user] = receivePurchaseFixtures();
    $chicken = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
    $pork = (new CreateProductAction)->execute('Media res de cochino', ProductType::RawMaterial, Unit::Piece, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-203',
        invoiceDate: now(),
        lines: [
            ['product_id' => $chicken->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
            ['product_id' => $pork->id, 'invoiced_weight_kg' => '45.000', 'line_amount' => '150.0000'],
        ],
    );
    $chickenLine = $purchase->lines[0];

    (new ReceivePurchaseAction)->execute($purchase, [
        $chickenLine->id => ['received_weight_kg' => '101.200'],
    ], $user->id);
})->throws(RuntimeException::class);

it('rejects receiving a purchase that is not draft [C12]', function () {
    [$supplier, $user] = receivePurchaseFixtures();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-204',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
    );
    $line = $purchase->lines->first();
    (new ReceivePurchaseAction)->execute($purchase, [$line->id => ['received_weight_kg' => '98.000']], $user->id);

    (new ReceivePurchaseAction)->execute(Purchase::find($purchase->id), [$line->id => ['received_weight_kg' => '98.000']], $user->id);
})->throws(RuntimeException::class);
