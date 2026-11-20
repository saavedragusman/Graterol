<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\ListAvailableRawMaterialBatchesAction;
use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\ReceivePurchaseAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Lotes recibidos antes de M3 quedaron sin product_id: la columna se agregó sin completar los
// datos existentes y el despiece (BR-09) no los reconocía como materia prima.

function runBackfill(): void
{
    $migration = require database_path('migrations/2026_11_20_000001_backfill_product_id_on_purchase_batches.php');
    $migration->up();
}

function receivedChickenBatch(): Batch
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $chicken = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: Supplier::factory()->create()->id,
        invoiceNumber: 'F-1',
        invoiceDate: now(),
        lines: [['product_id' => $chicken->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '280.0000']],
    );
    $line = $purchase->lines->first();
    (new ReceivePurchaseAction)->execute($purchase, [$line->id => ['received_weight_kg' => '98.000']], User::factory()->create()->id);

    return Batch::where('source_type', 'purchase_line')->where('source_id', $line->id)->firstOrFail();
}

it('fills the product of a purchase batch created before M3 so despiece can list it [BR-09]', function () {
    $batch = receivedChickenBatch();
    $productId = $batch->product_id;
    DB::table('batches')->where('id', $batch->id)->update(['product_id' => null]);
    expect((new ListAvailableRawMaterialBatchesAction)->execute())->toHaveCount(0);

    runBackfill();

    expect($batch->fresh()->product_id)->toBe($productId)
        ->and((new ListAvailableRawMaterialBatchesAction)->execute()->pluck('id')->all())->toBe([$batch->id]);
});

it('leaves batches that already have a product, and non-purchase batches, unchanged', function () {
    $batch = receivedChickenBatch();
    $before = DB::table('batches')->orderBy('id')->get()->toArray();

    runBackfill();

    expect(DB::table('batches')->orderBy('id')->get()->toArray())->toEqual($before);
});
