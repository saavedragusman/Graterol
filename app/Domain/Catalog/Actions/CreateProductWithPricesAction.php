<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use Illuminate\Support\Facades\DB;

/**
 * BR-04/BR-05: crea el producto y, si se indican, sus precios, en una transacción.
 */
class CreateProductWithPricesAction
{
    public function execute(
        string $name,
        ProductType $type,
        Unit $unit,
        ProductCategory $category,
        mixed $detailPrice = null,
        mixed $wholesalePrice = null,
        ?string $currency = null,
    ): Product {
        return DB::transaction(function () use ($name, $type, $unit, $category, $detailPrice, $wholesalePrice, $currency): Product {
            $product = (new CreateProductAction)->execute($name, $type, $unit, $category);
            (new SetProductPricesAction)->execute($product, $detailPrice, $wholesalePrice, $currency);

            return $product;
        });
    }
}
