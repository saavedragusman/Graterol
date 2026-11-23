<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductPrice;
use App\Domain\Catalog\PriceList;
use App\Domain\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;

/**
 * BR-04: fija el precio de un producto en una lista (detal o mayor).
 */
class SetProductPriceAction
{
    public function execute(
        Product $product,
        PriceList $priceList,
        string $amount,
        ?string $currency = null,
    ): ProductPrice {
        // S10: sin moneda explícita, la que se configuró en Settings.
        $currency ??= app(CompanySettings::class)->defaultCurrency();

        return DB::transaction(fn () => ProductPrice::updateOrCreate(
            ['product_id' => $product->id, 'price_list' => $priceList->value],
            ['amount' => $amount, 'currency' => $currency],
        ));
    }
}
