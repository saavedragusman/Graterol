<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\UpdateDraftPurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// DEC-01/C12: el monto facturado y el resto de la compra se pueden corregir mientras está en borrador.

function aDraftPurchase(): Purchase
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    return (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-100',
        invoiceDate: now(),
        lines: [
            ['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
        ],
        freightAmount: '15.0000',
    );
}

it('corrects the invoiced amount and freight while the purchase is draft [DEC-01]', function () {
    $purchase = aDraftPurchase();

    $updated = (new UpdateDraftPurchaseAction)->execute($purchase, [
        'freight_amount' => '12.0000',
    ], [
        ['product_id' => $purchase->lines->first()->product_id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '107.8000'],
    ]);

    expect($updated->freight_amount)->toBe('12.0000')
        ->and($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->line_amount)->toBe('107.8000');
});

it('rejects editing a purchase that is no longer draft [C12]', function () {
    $purchase = aDraftPurchase();
    $purchase->update(['status' => 'received']);

    (new UpdateDraftPurchaseAction)->execute($purchase->fresh(), ['freight_amount' => '1.0000']);
})->throws(RuntimeException::class);

it('ignores status, exchange_rate and supplier_id even if sent in header attributes [C12][C13]', function () {
    $purchase = aDraftPurchase();

    $updated = (new UpdateDraftPurchaseAction)->execute($purchase, [
        'status' => 'received',
        'exchange_rate' => '1.0000',
        'supplier_id' => 999999,
        'freight_amount' => '20.0000',
    ]);

    expect($updated->status)->toBe('draft')
        ->and($updated->exchange_rate)->toBe('85.5000')
        ->and($updated->supplier_id)->toBe($purchase->supplier_id)
        ->and($updated->freight_amount)->toBe('20.0000');
});

it('rejects replacing the lines of a draft with a derived product [DEC-05]', function () {
    $purchase = aDraftPurchase();
    $derived = (new CreateProductAction)->execute(
        'Pechuga deshuesada',
        ProductType::Derived,
        Unit::Kilogram,
        ProductCategory::NaturalMeat,
    );

    (new UpdateDraftPurchaseAction)->execute($purchase, [], [
        ['product_id' => $derived->id, 'invoiced_weight_kg' => '10.000', 'line_amount' => '50.0000'],
    ]);
})->throws(InvalidArgumentException::class);

it('keeps the original lines when the replacement is rejected [C11]', function () {
    $purchase = aDraftPurchase();
    $derived = (new CreateProductAction)->execute(
        'Pechuga deshuesada',
        ProductType::Derived,
        Unit::Kilogram,
        ProductCategory::NaturalMeat,
    );

    try {
        (new UpdateDraftPurchaseAction)->execute($purchase, [], [
            ['product_id' => $derived->id, 'invoiced_weight_kg' => '10.000', 'line_amount' => '50.0000'],
        ]);
    } catch (InvalidArgumentException) {
        // esperado
    }

    expect($purchase->fresh()->lines)->toHaveCount(1)
        ->and($purchase->fresh()->lines->first()->line_amount)->toBe('110.0000');
});
