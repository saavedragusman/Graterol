<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * C15: auditoría inmutable (quién, qué, cuándo, valores antes y después).
 * Tabla de solo inserción: no se actualiza ni se borra ninguna fila.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'reason',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
