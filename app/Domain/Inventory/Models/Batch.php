<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'code',
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
            'total_cost' => 'decimal:4',
            'exchange_rate' => 'decimal:4',
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
}
