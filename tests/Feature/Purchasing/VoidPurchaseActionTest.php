<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\ReceivePurchaseAction;
use App\Domain\Purchasing\Actions\VoidPurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-03/C12/C15: anular una compra recibida requiere permiso propio y deja auditoría.

function aReceivedPurchase(): Purchase
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    $purchase = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-300',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
    );
    $line = $purchase->lines->first();

    return (new ReceivePurchaseAction)->execute($purchase, [
        $line->id => ['received_weight_kg' => '98.000'],
    ], $user->id)->setAttribute('__voiding_user_id', $user->id);
}

it('voids a received purchase and nets its lot movements to zero [C10]', function () {
    $purchase = aReceivedPurchase();
    $userId = $purchase->__voiding_user_id;
    $batch = $purchase->lines->first()->batch;

    $voided = (new VoidPurchaseAction)->execute($purchase, 'Factura duplicada', $userId);

    expect($voided->status)->toBe('voided')
        ->and($voided->voided_by)->toBe($userId)
        ->and($voided->void_reason)->toBe('Factura duplicada');

    $sum = InventoryMovement::where('batch_id', $batch->id)->sum('quantity');
    expect((string) $sum)->toBe('0.000');
});

it('writes an immutable audit entry with who, what, when and the reason [C15]', function () {
    $purchase = aReceivedPurchase();
    $userId = $purchase->__voiding_user_id;

    (new VoidPurchaseAction)->execute($purchase, 'Factura duplicada', $userId);

    $entry = AuditLog::where('auditable_type', 'purchase')->where('auditable_id', $purchase->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->action)->toBe('purchase.void')
        ->and($entry->user_id)->toBe($userId)
        ->and($entry->reason)->toBe('Factura duplicada')
        ->and($entry->before['status'])->toBe('received')
        ->and($entry->after['status'])->toBe('voided');
});

it('rejects voiding when a lot already has movements beyond its entry [C10]', function () {
    $purchase = aReceivedPurchase();
    $userId = $purchase->__voiding_user_id;
    $batch = $purchase->lines->first()->batch;

    InventoryMovement::create([
        'batch_id' => $batch->id,
        'type' => 'butchery_out',
        'quantity' => '-10.000',
        'unit_cost' => $batch->unit_cost,
        'user_id' => $userId,
        'occurred_at' => now(),
    ]);

    (new VoidPurchaseAction)->execute($purchase, 'Factura duplicada', $userId);
})->throws(RuntimeException::class);

it('rejects voiding a purchase that was never received [C12]', function () {
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    $draft = (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-301',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
    );

    (new VoidPurchaseAction)->execute($draft, 'motivo', $user->id);
})->throws(RuntimeException::class);
