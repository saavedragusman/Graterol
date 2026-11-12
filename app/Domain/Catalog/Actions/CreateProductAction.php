<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use Illuminate\Support\Facades\DB;

/**
 * BR-04/BR-05: crea un producto y decide su IVA según la categoría.
 * Las carnes naturales son exentas y no aceptan una tasa distinta de cero;
 * charcutería y el resto se gravan a la tasa configurada salvo que se
 * indique una tasa explícita.
 */
class CreateProductAction
{
    public function execute(
        string $name,
        ProductType $type,
        Unit $unit,
        ProductCategory $category,
        ?string $taxRate = null,
    ): Product {
        return DB::transaction(function () use ($name, $type, $unit, $category, $taxRate) {
            $isExempt = $category === ProductCategory::NaturalMeat;
            $rate = $isExempt ? '0.00' : ($taxRate ?? config('graterol.charcuteria_vat_rate'));

            return Product::create([
                'name' => $name,
                'product_type' => $type,
                'unit' => $unit,
                'product_category' => $category,
                'tax_rate' => $rate,
                'is_tax_exempt' => $isExempt,
            ]);
        });
    }
}
