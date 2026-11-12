<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BR-02: registra la tasa BCV del día, automática o manual.
 */
class RegisterExchangeRateAction
{
    public function execute(
        string $rate,
        string $source,
        ?int $registeredBy = null,
        ?Carbon $effectiveAt = null,
    ): ExchangeRate {
        if (! in_array($source, ['automatic', 'manual'], true)) {
            throw new InvalidArgumentException("Fuente de tasa inválida: {$source}");
        }

        return DB::transaction(fn () => ExchangeRate::create([
            'rate' => $rate,
            'source' => $source,
            'registered_by' => $registeredBy,
            'effective_at' => $effectiveAt ?? now(),
        ]));
    }
}
