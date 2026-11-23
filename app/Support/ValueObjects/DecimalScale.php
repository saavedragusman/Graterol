<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use BcMath\Number;
use InvalidArgumentException;
use RoundingMode;
use ValueError;

/**
 * Utilidades de escala decimal para `Money` y `UnitAmount` (C9: BcMath\Number,
 * sin flotantes). Es el único lugar donde se redondea: half-up (al alejarse de
 * cero), siempre de forma explícita.
 */
final class DecimalScale
{
    /**
     * Interpreta un decimal tal como viene (no redondea). Lanza si no es numérico.
     */
    public static function parse(string|Number $value): Number
    {
        if ($value instanceof Number) {
            return $value;
        }

        try {
            return new Number(trim($value));
        } catch (ValueError) {
            throw new InvalidArgumentException("Valor numérico inválido: «{$value}».");
        }
    }

    /**
     * Valor exacto a `$scale` decimales: acepta ceros sobrantes ('110.0000' → '110.00')
     * pero rechaza cualquier decimal significativo de más, en vez de redondearlo.
     */
    public static function exact(string|Number $value, int $scale): Number
    {
        $number = self::parse($value);
        $rounded = $number->round($scale, RoundingMode::HalfAwayFromZero);

        if ($number->compare($rounded) !== 0) {
            throw new InvalidArgumentException("El valor «{$number}» tiene más de {$scale} decimales; debe redondearse de forma explícita.");
        }

        return self::fix($rounded, $scale);
    }

    /**
     * Redondeo half-up a `$scale` decimales. Único punto de redondeo.
     */
    public static function round(string|Number $value, int $scale): Number
    {
        return self::fix(self::parse($value)->round($scale, RoundingMode::HalfAwayFromZero), $scale);
    }

    /**
     * Fija la escala (el valor ya debe estar a esa escala o menos).
     */
    private static function fix(Number $value, int $scale): Number
    {
        return new Number(bcadd((string) $value, '0', $scale));
    }
}
