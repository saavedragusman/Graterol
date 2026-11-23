<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\User;
use Database\Factories\InventoryCountLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-16/DEC-07: línea de conteo, una por lote dentro de la sesión.
 * Pesos con 3 decimales exactos (C9).
 */
class InventoryCountLine extends Model
{
    /** @use HasFactory<InventoryCountLineFactory> */
    use HasFactory;

    protected $fillable = [
        'count_id',
        'batch_id',
        'system_quantity_kg',
        'counted_weight_kg',
        'difference_kg',
        'adjustment_movement_id',
        'counted_by',
        'counted_at',
        'responsible_user_id',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity_kg' => 'decimal:3',
            'counted_weight_kg' => 'decimal:3',
            'difference_kg' => 'decimal:3',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InventoryCount, $this>
     */
    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'count_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'adjustment_movement_id');
    }

    /**
     * Quien tecleó el peso.
     *
     * @return BelongsTo<User, $this>
     */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * Quien pesó y responde por la diferencia (DEC-05).
     *
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    protected static function newFactory(): InventoryCountLineFactory
    {
        return InventoryCountLineFactory::new();
    }
}
