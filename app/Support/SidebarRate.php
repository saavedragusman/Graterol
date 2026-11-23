<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Settings\Models\ExchangeRate;
use App\Support\ValueObjects\Money;

/**
 * BR-02: texto de la tarjeta «Tasa BCV» del menú lateral. Solo formatea; la tasa
 * vigente la decide ExchangeRate::current(). Fecha y hora en Caracas (C13).
 */
class SidebarRate
{
    /**
     * @return array{label: string, value: string, detail: string|null}
     */
    public static function forDisplay(): array
    {
        $rate = ExchangeRate::current();

        if ($rate === null) {
            return ['label' => 'Tasa BCV', 'value' => 'Sin tasa registrada', 'detail' => null];
        }

        $at = $rate->effective_at->copy()->setTimezone(DisplayTimezone::CARACAS);
        $isToday = $at->isSameDay(now(DisplayTimezone::CARACAS));
        $source = $rate->source === 'automatic' ? 'Carga automática' : 'Carga manual';
        $time = $at->format('g:i').' '.($at->format('A') === 'AM' ? 'a. m.' : 'p. m.');

        return [
            'label' => $isToday ? 'Tasa BCV de hoy' : 'Tasa BCV del '.$at->format('d/m'),
            'value' => Money::display((string) $rate->rate, 'VES'),
            'detail' => $source.' · '.$time,
        ];
    }
}
