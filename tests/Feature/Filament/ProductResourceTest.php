<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Actions\SetProductPriceAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\PriceList;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// M1/BR-04, BR-05: pantalla de productos; la regla vive en las Actions del catálogo.

function prUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('creates a derived product with detail and wholesale prices [BR-04]', function () {
    prUser('administrador');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Lomito',
            'product_type' => ProductType::Derived->value,
            'unit' => Unit::Kilogram->value,
            'product_category' => ProductCategory::NaturalMeat->value,
            'detail_price' => '9.5000',
            'wholesale_price' => '8.7500',
            'price_currency' => 'USD',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('name', 'Lomito')->firstOrFail();
    expect($product->product_type)->toBe(ProductType::Derived)
        ->and($product->priceFor(PriceList::Detail->value)->amount)->toBe('9.5000')
        ->and($product->priceFor(PriceList::Wholesale->value)->amount)->toBe('8.7500')
        ->and($product->priceFor(PriceList::Detail->value)->currency)->toBe('USD');
});

it('creates a product without prices so the cut can still be configured later [BR-04]', function () {
    prUser('administrador');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Costilla',
            'product_type' => ProductType::Derived->value,
            'unit' => Unit::Kilogram->value,
            'product_category' => ProductCategory::NaturalMeat->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('name', 'Costilla')->firstOrFail()->prices()->count())->toBe(0);
});

it('makes natural meat exempt and charcuterie taxable through CreateProductAction [BR-05]', function () {
    prUser('administrador');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Pollo',
            'product_type' => ProductType::RawMaterial->value,
            'unit' => Unit::Kilogram->value,
            'product_category' => ProductCategory::NaturalMeat->value,
        ])
        ->call('create');
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Jamón',
            'product_type' => ProductType::Resale->value,
            'unit' => Unit::Kilogram->value,
            'product_category' => ProductCategory::Charcuteria->value,
        ])
        ->call('create');

    expect(Product::where('name', 'Pollo')->first()->is_tax_exempt)->toBeTrue()
        ->and(Product::where('name', 'Jamón')->first()->is_tax_exempt)->toBeFalse()
        ->and(Product::where('name', 'Jamón')->first()->tax_rate)->toBe('16.00');
});

it('rejects a product without a name and stores nothing [BR-04]', function () {
    prUser('administrador');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => '',
            'product_type' => ProductType::Derived->value,
            'unit' => Unit::Kilogram->value,
            'product_category' => ProductCategory::NaturalMeat->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    expect(Product::count())->toBe(0);
});

it('updates the detail price of an existing product [BR-04]', function () {
    prUser('administrador');
    $product = (new CreateProductAction)->execute('Lomito', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['detail_price' => '10.0000', 'price_currency' => 'USD'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->priceFor(PriceList::Detail->value)->amount)->toBe('10.0000');
});

it('lists products only for users with catalog.manage [C14]', function () {
    prUser('cajero');

    Livewire::test(ListProducts::class)->assertForbidden();
});

it('shows the detail price with its currency, or "Sin precio" when missing [BR-04]', function () {
    prUser('administrador');
    $priced = (new CreateProductAction)->execute('Lomito', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);
    (new SetProductPriceAction)->execute($priced, PriceList::Detail, '9.5000', 'USD');
    (new CreateProductAction)->execute('Costilla', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);

    Livewire::test(ListProducts::class)
        ->assertSee('$ 9,50')
        ->assertSee('Sin precio')
        ->assertDontSee('Bs ');
});

it('shows the detail price in USD with the Bs line using today\'s BCV rate [BR-02]', function () {
    prUser('administrador');
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual');
    $priced = (new CreateProductAction)->execute('Lomito', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);
    (new SetProductPriceAction)->execute($priced, PriceList::Detail, '9.5000', 'USD');
    (new CreateProductAction)->execute('Costilla', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);

    Livewire::test(ListProducts::class)
        ->assertSee('$ 9,50')
        ->assertSee('Bs 812,25')
        ->assertSee('Sin precio');
});
