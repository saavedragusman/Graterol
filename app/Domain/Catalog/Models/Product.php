<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'product_type',
        'unit',
        'product_category',
        'tax_rate',
        'is_tax_exempt',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'unit' => Unit::class,
            'product_category' => ProductCategory::class,
            'tax_rate' => 'decimal:2',
            'is_tax_exempt' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function priceFor(string $priceList): ?ProductPrice
    {
        return $this->prices()->where('price_list', $priceList)->first();
    }
}
