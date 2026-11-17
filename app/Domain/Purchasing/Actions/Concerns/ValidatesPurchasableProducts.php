<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions\Concerns;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductType;
use InvalidArgumentException;

/**
 * DEC-05: solo se puede comprar materia prima o reventa; los derivados
 * salen únicamente del despiece propio (M3). Compartido entre crear y
 * corregir una compra, para que no se pueda esquivar editando el borrador.
 */
trait ValidatesPurchasableProducts
{
    /**
     * @param  array<int, array{product_id: int}>  $lines
     */
    private function assertPurchasableProducts(array $lines): void
    {
        $productIds = array_unique(array_column($lines, 'product_id'));

        $invalid = Product::whereIn('id', $productIds)
            ->whereNot('product_type', ProductType::RawMaterial->value)
            ->whereNot('product_type', ProductType::Resale->value)
            ->exists();

        if ($invalid) {
            throw new InvalidArgumentException('Solo se puede comprar materia prima o reventa (DEC-05); los derivados salen del despiece.');
        }
    }
}
