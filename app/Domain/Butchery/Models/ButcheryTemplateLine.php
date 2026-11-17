<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-08: un corte (producto tipo derived) con su % estándar dentro de una plantilla.
 */
class ButcheryTemplateLine extends Model
{
    protected $fillable = [
        'template_id',
        'cut_product_id',
        'expected_yield_percent',
    ];

    protected function casts(): array
    {
        return [
            'expected_yield_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ButcheryTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ButcheryTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function cutProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cut_product_id');
    }
}
