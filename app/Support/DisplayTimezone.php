<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zona horaria de presentación (C13): los timestamps se guardan en UTC
 * (config/app.php no cambia) y se muestran siempre en America/Caracas.
 * Es fija, no una configuración (S11). La PWA usa la misma zona en resources/js/lib/format.ts.
 */
final class DisplayTimezone
{
    public const CARACAS = 'America/Caracas';
}
