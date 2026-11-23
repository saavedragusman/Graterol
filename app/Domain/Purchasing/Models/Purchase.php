<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-06/BR-07: nace en `draft` (Filament) y pasa a `received` al pesar
 * en la PWA (DEC-01). Guarda la tasa BCV del día (C13, BR-02).
 */
class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'currency',
        'exchange_rate',
        'freight_amount',
        'other_costs_amount',
        'other_costs_note',
        'status',
        'created_by',
        'received_by',
        'voided_by',
        'received_at',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'supplier_invoice_date' => 'date',
            'exchange_rate' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'other_costs_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
