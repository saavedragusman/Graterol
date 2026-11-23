<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\User;
use Database\Factories\InventoryCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-16/DEC-07: sesión de conteo físico (draft → confirmed | cancelled).
 * Una sesión confirmada no se edita (C12); un error se corrige con otra sesión.
 */
class InventoryCount extends Model
{
    /** @use HasFactory<InventoryCountFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'status',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InventoryCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class, 'count_id');
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
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected static function newFactory(): InventoryCountFactory
    {
        return InventoryCountFactory::new();
    }
}
