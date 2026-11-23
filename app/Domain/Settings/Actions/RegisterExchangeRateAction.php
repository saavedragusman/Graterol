<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\ExchangeRate;
use BcMath\Number;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BR-02: registra la tasa BCV del día, automática o manual.
 */
class RegisterExchangeRateAction
{
    /** Decimales de la tasa (BR-02). */
    public const SCALE = 2;

    public function execute(
        string $rate,
        string $source,
        ?int $registeredBy = null,
        ?Carbon $effectiveAt = null,
    ): ExchangeRate {
        if (! in_array($source, ['automatic', 'manual'], true)) {
            throw new InvalidArgumentException("Fuente de tasa inválida: {$source}");
        }

        // BR-02: la tasa se guarda y se usa con 2 decimales (half-up), no con los 4 de la fuente.
        $rate = (string) (new Number($rate))->round(self::SCALE);

        if ((new Number($rate))->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        return DB::transaction(fn () => ExchangeRate::create([
            'rate' => $rate,
            'source' => $source,
            'registered_by' => $registeredBy,
            'effective_at' => $effectiveAt ?? now(),
        ]));
    }
}
