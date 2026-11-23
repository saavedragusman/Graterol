<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'product_id',
        'source_type',
        'source_id',
        'unit',
        'pieces',
        'unit_cost',
        'total_cost',
        'currency',
        'exchange_rate',
        'received_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'exchange_rate' => 'decimal:2',
            'received_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): BatchFactory
    {
        return BatchFactory::new();
    }
}
