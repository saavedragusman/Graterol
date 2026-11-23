<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-09 a BR-12: línea de una orden de despiece. % esperado congelado al
 * abrir (DEC-03); peso real, precio de referencia, costo asignado y
 * desviación se llenan al cerrar.
 */
class ButcheryOrderLine extends Model
{
    protected $fillable = [
        'order_id',
        'cut_product_id',
        'expected_yield_percent',
        'expected_weight_kg',
        'actual_weight_kg',
        'reference_price_per_kg',
        'allocated_cost',
        'deviation_percent',
        'output_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_yield_percent' => 'decimal:2',
            'expected_weight_kg' => 'decimal:3',
            'actual_weight_kg' => 'decimal:3',
            'reference_price_per_kg' => 'decimal:4',
            'allocated_cost' => 'decimal:4',
            'deviation_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ButcheryOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ButcheryOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function cutProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cut_product_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function outputBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'output_batch_id');
    }
}
