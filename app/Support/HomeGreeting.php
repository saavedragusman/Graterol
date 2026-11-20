<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Textos del Inicio de la oficina: saludo según la hora y fecha en español,
 * ambos en America/Caracas (C13).
 */
class HomeGreeting
{
    public static function greeting(?CarbonInterface $now = null): string
    {
        $hour = self::local($now)->hour;

        return match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    /** «Lunes 9 de noviembre de 2026» (design.md §4). */
    public static function dateLine(?CarbonInterface $now = null): string
    {
        return ucfirst(self::local($now)->locale('es')->translatedFormat('l j \d\e F \d\e Y'));
    }

    private static function local(?CarbonInterface $now): CarbonInterface
    {
        return ($now ?? now())->copy()->setTimezone(DisplayTimezone::CARACAS);
    }
}
