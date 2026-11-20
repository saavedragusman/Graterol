<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-05: el IVA va por producto. Carnes naturales exentas; charcutería 16 % por defecto.

it('is always tax-exempt for natural meat, even if a rate is passed [BR-05]', function () {
    $product = (new CreateProductAction)->execute(
        'Res en vara',
        ProductType::RawMaterial,
        Unit::Kilogram,
        ProductCategory::NaturalMeat,
        taxRate: '16.00',
    );

    expect($product->is_tax_exempt)->toBeTrue()
        ->and($product->tax_rate)->toBe('0.00');
});

it('defaults charcuteria to the configured VAT rate and is not exempt [BR-05]', function () {
    $product = (new CreateProductAction)->execute('Jamón', ProductType::Resale, Unit::Kilogram, ProductCategory::Charcuteria);

    expect($product->is_tax_exempt)->toBeFalse()
        ->and($product->tax_rate)->toBe(CompanySettings::DEFAULT_CHARCUTERIA_VAT_RATE);
});

it('defaults charcuteria to the VAT rate configured in settings [BR-05][C6]', function () {
    app(UpdateSettingsAction::class)->execute([CompanySettings::CHARCUTERIA_VAT_RATE => '8'], null);

    $product = (new CreateProductAction)->execute('Jamón', ProductType::Resale, Unit::Kilogram, ProductCategory::Charcuteria);

    expect($product->tax_rate)->toBe('8.00');
});

it('respects an explicit VAT rate override for charcuteria [BR-05]', function () {
    $product = (new CreateProductAction)->execute(
        'Salami importado',
        ProductType::Resale,
        Unit::Kilogram,
        ProductCategory::Charcuteria,
        taxRate: '8.00',
    );

    expect($product->is_tax_exempt)->toBeFalse()
        ->and($product->tax_rate)->toBe('8.00');
});
