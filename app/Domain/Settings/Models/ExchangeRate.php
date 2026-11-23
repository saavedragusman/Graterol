<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Models\User;
use App\Support\DisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BR-02: tasa BCV diaria, con carga automática y respaldo manual.
 * Tabla de solo inserción: no se actualiza ni se borra ningún registro.
 *
 * @property string $rate
 * @property string $source
 * @property Carbon $effective_at
 * @property int|null $registered_by
 */
class ExchangeRate extends Model
{
    protected $fillable = ['rate', 'source', 'registered_by', 'effective_at'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
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

    /**
     * S3: ¿hay una tasa automática vigente hoy? «Hoy» es el día de Caracas (C13);
     * se compara contra sus límites convertidos a UTC. Única fuente de esta regla.
     */
    public static function hasAutomaticToday(): bool
    {
        $start = now(DisplayTimezone::CARACAS)->startOfDay();

        return self::where('source', 'automatic')
            ->where('effective_at', '>=', $start->copy()->utc())
            ->where('effective_at', '<', $start->copy()->addDay()->utc())
            ->where('effective_at', '<=', now())
            ->exists();
    }
}
