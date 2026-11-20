<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\ExchangeRate;
use App\Models\User;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * S4 + S5 (BR-02, C9, C15): registra a mano la tasa BCV (Bs por USD) y la audita.
 * Delega el insert en RegisterExchangeRateAction; la fila nunca se edita después.
 */
class RegisterManualExchangeRateAction
{
    /** Tope de dígitos enteros (numeric(18,4) admite más; esto frena errores de tecleo). */
    private const MAX_INTEGER_DIGITS = 9;

    public function __construct(private readonly RegisterExchangeRateAction $register) {}

    public function execute(string $rate, User $actor): ExchangeRate
    {
        $normalized = self::normalize($rate);

        return DB::transaction(function () use ($normalized, $actor): ExchangeRate {
            $previous = ExchangeRate::current();

            $new = $this->register->execute($normalized, 'manual', $actor->id);

            AuditLog::create([
                'auditable_type' => 'exchange_rate',
                'auditable_id' => $new->id,
                'action' => 'exchange_rate.registered',
                'user_id' => $actor->id,
                'before' => $previous === null ? null : self::snapshot($previous),
                'after' => self::snapshot($new),
            ]);

            return $new;
        });
    }

    /**
     * Decimal en texto (coma o punto), mayor que cero, hasta 4 decimales; sin tipos de punto flotante (C9).
     */
    private static function normalize(string $rate): string
    {
        $value = str_replace(',', '.', trim($rate));

        if (! preg_match('/^\d{1,'.self::MAX_INTEGER_DIGITS.'}(\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('La tasa debe ser un número positivo con hasta 4 decimales y menor a 1.000.000.000.');
        }

        if ((new Number($value))->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        return (string) (new Number($value))->round(4);
    }

    /**
     * @return array{rate: string, source: string, effective_at: string}
     */
    private static function snapshot(ExchangeRate $rate): array
    {
        return [
            'rate' => (string) $rate->rate,
            'source' => $rate->source,
            'effective_at' => $rate->effective_at->toIso8601String(),
        ];
    }
}
