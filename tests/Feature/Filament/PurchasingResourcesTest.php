<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\ReceivePurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\PurchaseResource;
use App\Filament\Resources\PurchaseResource\Pages\CreatePurchase;
use App\Filament\Resources\PurchaseResource\Pages\EditPurchase;
use App\Filament\Resources\PurchaseResource\Pages\ListPurchases;
use App\Filament\Resources\PurchaseResource\Pages\ViewPurchase;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// M2/DEC-01: Filament crea y corrige borradores y anula recibidas; la regla vive en las Actions.

function fpUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

function fpProduct(ProductType $type = ProductType::RawMaterial, string $name = 'Pollo entero'): Product
{
    return (new CreateProductAction)->execute($name, $type, Unit::Kilogram, ProductCategory::NaturalMeat);
}

function fpDraft(): Purchase
{
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');

    return (new CreatePurchaseAction)->execute(
        supplierId: Supplier::factory()->create()->id,
        invoiceNumber: 'F-100',
        invoiceDate: now(),
        lines: [['product_id' => fpProduct()->id, 'invoiced_weight_kg' => '100.000', 'line_amount' => '110.0000']],
        // Factura en Bs explícita: la moneda por defecto sale de Settings (S10).
        currency: 'VES',
    );
}

function fpReceived(): Purchase
{
    $purchase = fpDraft();
    $line = $purchase->lines->first();

    return (new ReceivePurchaseAction)->execute($purchase, [
        $line->id => ['received_weight_kg' => '98.400'],
    ], User::factory()->create()->id);
}

it('creates a purchase with its lines through CreatePurchaseAction [DEC-01]', function () {
    fpUser('administrador');
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = fpProduct();

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'F-777',
            'supplier_invoice_date' => '2026-11-10',
            'freight_amount' => '20',
            'lines' => [['product_id' => $product->id, 'invoiced_weight_kg' => '50.500', 'invoiced_pieces' => '4', 'line_amount' => '300.25']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::where('supplier_invoice_number', 'F-777')->firstOrFail();
    expect($purchase->status)->toBe('draft')
        ->and($purchase->exchange_rate)->toBe('85.50')
        ->and($purchase->freight_amount)->toBe('20.00')
        ->and($purchase->created_by)->not->toBeNull()
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->lines->first()->invoiced_weight_kg)->toBe('50.500')
        ->and($purchase->lines->first()->line_amount)->toBe('300.25')
        ->and($purchase->lines->first()->invoiced_pieces)->toBe(4);
});

it('guarda la moneda elegida y la tasa BCV vigente; por defecto USD [C13]', function () {
    fpUser('administrador');
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $product = fpProduct();
    $line = [['product_id' => $product->id, 'invoiced_weight_kg' => '10.000', 'line_amount' => '100']];

    Livewire::test(CreatePurchase::class)
        ->assertFormSet(['currency' => 'USD'])
        ->fillForm(['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'F-USD', 'supplier_invoice_date' => '2026-11-10', 'lines' => $line])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreatePurchase::class)
        ->fillForm(['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'F-VES', 'supplier_invoice_date' => '2026-11-10', 'currency' => 'VES', 'lines' => $line])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Purchase::where('supplier_invoice_number', 'F-USD')->firstOrFail()->currency)->toBe('USD')
        ->and(Purchase::where('supplier_invoice_number', 'F-VES')->firstOrFail()->currency)->toBe('VES')
        ->and(Purchase::where('supplier_invoice_number', 'F-VES')->firstOrFail()->exchange_rate)->toBe('85.50');
});

it('does not offer derived products and rejects them on submit [DEC-05]', function () {
    fpUser('administrador');
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $supplier = Supplier::factory()->create();
    $derived = fpProduct(ProductType::Derived, 'Pechuga');
    $resale = fpProduct(ProductType::Resale, 'Refresco');

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'F-888',
            'supplier_invoice_date' => '2026-11-10',
            'lines' => [['product_id' => $derived->id, 'invoiced_weight_kg' => '5', 'line_amount' => '10']],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Purchase::where('supplier_invoice_number', 'F-888')->exists())->toBeFalse()
        ->and(Product::where('id', $resale->id)->exists())->toBeTrue();
});

it('keeps the purchase unsaved and notifies when there is no BCV rate [BR-02]', function () {
    fpUser('administrador');
    $supplier = Supplier::factory()->create();
    $product = fpProduct();

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'F-999',
            'supplier_invoice_date' => '2026-11-10',
            'lines' => [['product_id' => $product->id, 'invoiced_weight_kg' => '5', 'line_amount' => '10']],
        ])
        ->call('create');

    expect(Purchase::count())->toBe(0);
});

it('edits a draft purchase through UpdateDraftPurchaseAction [C12]', function () {
    fpUser('administrador');
    $purchase = fpDraft();
    $other = fpProduct(ProductType::Resale, 'Refresco');

    Livewire::test(EditPurchase::class, ['record' => $purchase->getKey()])
        ->fillForm([
            'supplier_invoice_number' => 'F-100-B',
            'lines' => [['product_id' => $other->id, 'invoiced_weight_kg' => '12.000', 'line_amount' => '40']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $purchase->refresh()->load('lines');
    expect($purchase->supplier_invoice_number)->toBe('F-100-B')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->lines->first()->product_id)->toBe($other->id);
});

it('blocks editing a received purchase [C12]', function () {
    fpUser('administrador');
    $purchase = fpReceived();

    expect(PurchaseResource::canEdit($purchase))->toBeFalse();

    Livewire::test(EditPurchase::class, ['record' => $purchase->getKey()])->assertForbidden();
});

it('shows invoiced, received weight, difference and batch cost on a received purchase [BR-06, BR-07]', function () {
    fpUser('administrador');
    $purchase = fpReceived();

    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->assertSuccessful()
        ->assertSee('100.000')
        ->assertSee('98.400')
        ->assertSee('(diferencia: -1.600)')
        ->assertSee('Costo por kg del lote');
});

it('shows purchase amounts in USD with the Bs line using the purchase frozen rate [C13][BR-02]', function () {
    fpUser('administrador');
    $purchase = fpReceived();

    // Factura en Bs (por defecto) con tasa congelada 85,50: línea de Bs 110,00 = $ 1,29.
    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->assertSee('$ 1,29')
        ->assertSee('Bs 110,00');
});

it('voids a received purchase only with a reason [BR-03, C12]', function () {
    $admin = fpUser('administrador');
    $purchase = fpReceived();

    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->callAction('void', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
    expect($purchase->fresh()->status)->toBe('received');

    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->callAction('void', ['reason' => 'Factura duplicada'])
        ->assertHasNoActionErrors();

    $purchase->refresh();
    expect($purchase->status)->toBe('voided')
        ->and($purchase->void_reason)->toBe('Factura duplicada')
        ->and($purchase->voided_by)->toBe($admin->id);
});

it('hides the void action for drafts and for users without purchasing.void [BR-03]', function () {
    fpUser('administrador');
    $draft = fpDraft();

    Livewire::test(ViewPurchase::class, ['record' => $draft->getKey()])->assertActionHidden('void');

    $received = fpReceived();
    fpUser('carnicero');

    Livewire::test(ViewPurchase::class, ['record' => $received->getKey()])->assertActionHidden('void');
});

it('lists purchases and filters them by status [DEC-01]', function () {
    fpUser('administrador');
    $draft = fpDraft();
    $received = fpReceived();

    Livewire::test(ListPurchases::class)
        ->assertCanSeeTableRecords([$draft, $received])
        ->filterTable('status', 'received')
        ->assertCanSeeTableRecords([$received])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('does not let carnicero create purchases from the panel [BR-03]', function () {
    fpUser('carnicero');

    expect(PurchaseResource::canCreate())->toBeFalse();
});

it('creates and edits suppliers for users who manage them [BR-03]', function () {
    fpUser('administrador');

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Cárnicos del Sur', 'tax_id' => 'J-12345678-9', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $supplier = Supplier::where('name', 'Cárnicos del Sur')->firstOrFail();

    Livewire::test(EditSupplier::class, ['record' => $supplier->getKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($supplier->fresh()->is_active)->toBeFalse();
});

it('forbids creating suppliers without purchasing.manage-suppliers [BR-03]', function () {
    fpUser('carnicero');

    Livewire::test(CreateSupplier::class)->assertForbidden();
});

it('preselects the configured default currency in the purchase form [S10]', function () {
    fpUser('administrador');
    (new UpdateSettingsAction)->execute([CompanySettings::DEFAULT_CURRENCY => 'VES'], null);

    Livewire::test(CreatePurchase::class)->assertFormSet(['currency' => 'VES']);
});
