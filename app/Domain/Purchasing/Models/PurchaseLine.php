<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-06: peso facturado, recibido y su diferencia, siempre en kg
 * (DEC-06: un lote nace por línea, con el número de piezas adentro).
 */
class PurchaseLine extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'invoiced_weight_kg',
        'invoiced_pieces',
        'line_amount',
        'received_weight_kg',
        'received_pieces',
        'weight_difference_kg',
        'allocated_freight',
        'allocated_other_costs',
        'expires_on',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'invoiced_weight_kg' => 'decimal:3',
            'line_amount' => 'decimal:2',
            'received_weight_kg' => 'decimal:3',
            'weight_difference_kg' => 'decimal:3',
            'allocated_freight' => 'decimal:2',
            'allocated_other_costs' => 'decimal:2',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
