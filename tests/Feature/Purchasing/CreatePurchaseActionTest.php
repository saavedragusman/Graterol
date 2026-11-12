<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// BR-02/BR-07: la compra nace en borrador, con la tasa BCV del día guardada.

function rawMaterialProduct(string $name = 'Pollo entero')
{
    return (new CreateProductAction)->execute($name, ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
}

it('creates a purchase in draft with the current exchange rate [BR-02]', function () {
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = rawMaterialProduct();

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-001',
        invoiceDate: now(),
        lines: [
            ['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
        ],
        freightAmount: '15.0000',
    );

    expect($purchase->status)->toBe('draft')
        ->and($purchase->exchange_rate)->toBe('85.5000')
        ->and($purchase->freight_amount)->toBe('15.0000')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->lines->first()->invoiced_weight_kg)->toBe('100.000')
        ->and($purchase->lines->first()->line_amount)->toBe('110.0000')
        ->and($purchase->lines->first()->batch_id)->toBeNull();
});

it('rejects creating a purchase without a registered exchange rate [BR-02]', function () {
    $supplier = Supplier::factory()->create();
    $product = rawMaterialProduct();

    (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-002',
        invoiceDate: now(),
        lines: [
            ['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
        ],
    );
})->throws(RuntimeException::class);

it('rejects buying a derived product, only raw material or resale [DEC-05]', function () {
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $derived = (new CreateProductAction)->execute('Pechuga deshuesada', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);

    (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-003',
        invoiceDate: now(),
        lines: [
            ['product_id' => $derived->id, 'invoiced_weight_kg' => '10.000', 'line_amount' => '50.0000'],
        ],
    );
})->throws(InvalidArgumentException::class);

it('creates nothing when a line fails inside the transaction [C11]', function () {
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = rawMaterialProduct();
    $derived = (new CreateProductAction)->execute('Pechuga deshuesada', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);

    try {
        (new CreatePurchaseAction)->execute(
            supplierId: $supplier->id,
            invoiceNumber: 'F-004',
            invoiceDate: now(),
            lines: [
                ['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
                ['product_id' => $derived->id, 'invoiced_weight_kg' => '10.000', 'line_amount' => '50.0000'],
            ],
        );
    } catch (InvalidArgumentException) {
        // esperado
    }

    expect(DB::table('purchases')->count())->toBe(0)
        ->and(DB::table('purchase_lines')->count())->toBe(0);
});
