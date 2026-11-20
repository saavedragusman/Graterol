<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Actions\CreateProductWithPricesAction;
use App\Domain\Catalog\Actions\SetProductPriceAction;
use App\Domain\Catalog\Actions\SetProductPricesAction;
use App\Domain\Catalog\Actions\UpdateProductAction;
use App\Domain\Catalog\PriceList;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-04: tipos de producto, unidad y listas de precio.

it('creates a product with its type, unit and category [BR-04]', function (ProductType $type, Unit $unit) {
    $action = new CreateProductAction;

    $product = $action->execute('Pollo entero', $type, $unit, ProductCategory::NaturalMeat);

    expect($product->product_type)->toBe($type)
        ->and($product->unit)->toBe($unit);
})->with([
    'materia prima en kg' => [ProductType::RawMaterial, Unit::Kilogram],
    'derivado en kg' => [ProductType::Derived, Unit::Kilogram],
    'reventa en pieza' => [ProductType::Resale, Unit::Piece],
]);

it('keeps detail and wholesale prices distinct for the same product [BR-04]', function () {
    $product = (new CreateProductAction)->execute('Chuleta de cerdo', ProductType::Derived, Unit::Kilogram, ProductCategory::NaturalMeat);
    $setPrice = new SetProductPriceAction;

    $setPrice->execute($product, PriceList::Detail, '450.0000');
    $setPrice->execute($product, PriceList::Wholesale, '400.0000');

    expect($product->priceFor(PriceList::Detail->value)->amount)->toBe('450.0000')
        ->and($product->priceFor(PriceList::Wholesale->value)->amount)->toBe('400.0000');
});

it('updates instead of duplicating a price for the same product and price list [BR-04]', function () {
    $product = (new CreateProductAction)->execute('Mortadela', ProductType::Resale, Unit::Kilogram, ProductCategory::Charcuteria);
    $setPrice = new SetProductPriceAction;

    $setPrice->execute($product, PriceList::Detail, '100.0000');
    $setPrice->execute($product, PriceList::Detail, '120.0000');

    expect($product->prices()->where('price_list', PriceList::Detail->value)->count())->toBe(1)
        ->and($product->priceFor(PriceList::Detail->value)->amount)->toBe('120.0000');
});

// M1 / S10: la moneda por defecto de los precios sale de Settings.
it('defaults the price currency to the configured default currency [S10]', function () {
    $product = (new CreateProductAction)->execute('Pollo entero', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat);
    $setPrice = new SetProductPriceAction;

    $setPrice->execute($product, PriceList::Detail, '10.0000');
    expect($product->priceFor(PriceList::Detail->value)->currency)->toBe('USD');

    (new UpdateSettingsAction)->execute([CompanySettings::DEFAULT_CURRENCY => 'VES'], null);

    $setPrice->execute($product, PriceList::Detail, '10.0000');
    (new SetProductPricesAction)->execute($product, null, '9');
    $other = (new CreateProductWithPricesAction)->execute('Pernil', ProductType::RawMaterial, Unit::Kilogram, ProductCategory::NaturalMeat, '5', '4');
    $updated = (new UpdateProductAction)->execute($other, 'Pernil', true, '6', null);

    expect($product->priceFor(PriceList::Detail->value)->currency)->toBe('VES')
        ->and($product->priceFor(PriceList::Wholesale->value)->currency)->toBe('VES')
        ->and($updated->priceFor(PriceList::Detail->value)->currency)->toBe('VES')
        ->and($updated->priceFor(PriceList::Wholesale->value)->currency)->toBe('VES');
});
