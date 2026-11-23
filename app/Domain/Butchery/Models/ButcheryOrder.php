<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Models;

use App\Domain\Inventory\Models\Batch;
use App\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-09 a BR-12: orden de despiece. open -> closed | voided (C12: cerrada
 * no se edita, se anula).
 */
class ButcheryOrder extends Model
{
    protected $fillable = [
        'code',
        'batch_id',
        'template_id',
        'input_weight_kg',
        'expected_shrinkage_percent',
        'butcher_id',
        'status',
        'actual_shrinkage_kg',
        'closed_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'input_weight_kg' => 'decimal:3',
            'expected_shrinkage_percent' => 'decimal:2',
            'actual_shrinkage_kg' => 'decimal:3',
            'closed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<ButcheryTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ButcheryTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function butcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'butcher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<ButcheryOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ButcheryOrderLine::class, 'order_id');
    }

    /**
     * Costo de la entrada de ESTA orden: costo unitario del lote × kg que toma
     * la orden (DEC-07). Una orden puede tomar solo parte del lote, así que no
     * es el costo total del lote. Lo usan el cierre (BR-12) y la pantalla.
     */
    public function inputCost(): Money
    {
        return Money::fromString((string) $this->batch->unit_cost)->mul((string) $this->input_weight_kg);
    }
}
