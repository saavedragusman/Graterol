<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-02: tasa BCV diaria, con carga automática y respaldo manual.
 * Tabla de solo inserción: no se actualiza ni se borra ningún registro.
 */
class ExchangeRate extends Model
{
    protected $fillable = ['rate', 'source', 'registered_by', 'effective_at'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * La tasa vigente: la más reciente cuya vigencia ya empezó.
     */
    public static function current(): ?self
    {
        return self::where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->first();
    }
}
