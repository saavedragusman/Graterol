<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// M2/DEC-01/BR-06/BR-07: recepción con pesaje en la PWA (Inertia).

function carniceroUser(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'carnicero')->first());

    return $user;
}

function aDraftPurchaseForReceipt(): Purchase
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);

    return (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-500',
        invoiceDate: now(),
        lines: [['product_id' => $product->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
    );
}

function aDraftPurchaseWithTwoLinesForReceipt(): Purchase
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $chicken = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
    $pork = (new CreateProductAction)->execute('Media res de cochino', ProductType::RawMaterial, Unit::Piece, ProductCategory::NaturalMeat);

    return (new CreatePurchaseAction)->execute(
        supplierId: $supplier->id,
        invoiceNumber: 'F-501',
        invoiceDate: now(),
        lines: [
            ['product_id' => $chicken->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000'],
            ['product_id' => $pork->id, 'invoiced_weight_kg' => '45.000', 'line_amount' => '150.0000'],
        ],
    );
}

it('lists draft purchases pending reception [BR-06]', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseForReceipt();

    $this->actingAs($user)
        ->get('/purchasing/receipts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchasing/ReceiptsIndex')
            ->has('purchases', 1)
            ->where('purchases.0.id', $purchase->id)
        );
});

it('shows a draft purchase with its lines to weigh', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseForReceipt();

    $this->actingAs($user)
        ->get("/purchasing/receipts/{$purchase->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchasing/ReceiptShow')
            ->where('purchase.id', $purchase->id)
            ->has('purchase.lines', 1)
        );
});

it('confirms a reception and creates the lot [BR-06][BR-07]', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseForReceipt();
    $line = $purchase->lines->first();

    $this->actingAs($user)
        ->post("/purchasing/receipts/{$purchase->id}/confirm", [
            'lines' => [
                ['id' => $line->id, 'received_weight_kg' => '98.000'],
            ],
        ])
        ->assertRedirect('/purchasing/receipts');

    expect($purchase->fresh()->status)->toBe('received')
        ->and($line->fresh()->batch_id)->not->toBeNull();
});

it('rejects confirming without the receive permission [C14]', function () {
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'cajero')->first());
    $purchase = aDraftPurchaseForReceipt();
    $line = $purchase->lines->first();

    $this->actingAs($user)
        ->post("/purchasing/receipts/{$purchase->id}/confirm", [
            'lines' => [['id' => $line->id, 'received_weight_kg' => '98.000']],
        ])
        ->assertForbidden();
});

it('rejects weighing without every line having a weight [BR-06]', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseForReceipt();

    $this->actingAs($user)
        ->post("/purchasing/receipts/{$purchase->id}/confirm", [
            'lines' => [],
        ])
        ->assertInvalid(['lines']);
});

it('rejects confirming a purchase when a line is missing, not just when all are missing [BR-06]', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseWithTwoLinesForReceipt();
    $firstLine = $purchase->lines->first();

    $this->actingAs($user)
        ->post("/purchasing/receipts/{$purchase->id}/confirm", [
            'lines' => [
                ['id' => $firstLine->id, 'received_weight_kg' => '98.000'],
            ],
        ])
        ->assertInvalid(['lines']);

    expect($purchase->fresh()->status)->toBe('draft');
});

it('rejects viewing the receipt page for a purchase that is no longer draft [C12]', function () {
    $user = carniceroUser();
    $purchase = aDraftPurchaseForReceipt();
    $purchase->update(['status' => 'received']);

    $this->actingAs($user)
        ->get("/purchasing/receipts/{$purchase->id}")
        ->assertNotFound();
});
