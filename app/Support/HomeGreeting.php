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
    /** Saludo según la hora; con nombre agrega solo el primero: «Buenos días, María». */
    public static function greeting(?CarbonInterface $now = null, ?string $fullName = null): string
    {
        $hour = self::local($now)->hour;

        $greeting = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $first = self::firstName($fullName);

        return $first === null ? $greeting : $greeting.', '.$first;
    }

    private static function firstName(?string $fullName): ?string
    {
        $first = preg_split('/\s+/u', trim((string) $fullName), 2)[0] ?? '';

        return $first === '' ? null : mb_strtoupper(mb_substr($first, 0, 1)).mb_substr($first, 1);
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
