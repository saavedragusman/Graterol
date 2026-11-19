<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * BR-04: edita nombre, estado y precios. Tipo, unidad y categoría no cambian
 * después de crear, porque alterarían el IVA decidido por BR-05.
 */
class UpdateProductAction
{
    public function execute(
        Product $product,
        string $name,
        bool $active,
        mixed $detailPrice = null,
        mixed $wholesalePrice = null,
        string $currency = 'USD',
    ): Product {
        return DB::transaction(function () use ($product, $name, $active, $detailPrice, $wholesalePrice, $currency): Product {
            $product->update(['name' => $name, 'active' => $active]);
            (new SetProductPricesAction)->execute($product, $detailPrice, $wholesalePrice, $currency);

            return $product;
        });
    }
}
