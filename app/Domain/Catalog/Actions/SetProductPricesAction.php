<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\PriceList;
use Illuminate\Support\Facades\DB;

/**
 * BR-04: fija los precios detal y mayor de un producto en una sola transacción.
 * Un precio vacío no se toca: no se borra ni se inventa uno.
 */
class SetProductPricesAction
{
    public function execute(Product $product, mixed $detail, mixed $wholesale, ?string $currency = null): void
    {
        DB::transaction(function () use ($product, $detail, $wholesale, $currency): void {
            $setPrice = new SetProductPriceAction;

            foreach ([PriceList::Detail->value => $detail, PriceList::Wholesale->value => $wholesale] as $list => $amount) {
                if (filled($amount)) {
                    $setPrice->execute(
                        $product,
                        PriceList::from($list),
                        bcadd((string) $amount, '0', 4),
                        $currency,
                    );
                }
            }
        });
    }
}
