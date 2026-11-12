<?php

declare(strict_types=1);

namespace App\Support\Formatting;

final class EsVeNumberFormatter
{
    public static function format(string $decimalValue, int $scale): string
    {
        $negative = str_starts_with($decimalValue, '-');
        $unsigned = ltrim($decimalValue, '-');

        [$integer, $decimals] = array_pad(explode('.', $unsigned, 2), 2, '');
        $decimals = str_pad(substr($decimals, 0, $scale), $scale, '0');

        $integer = ltrim($integer, '0') ?: '0';
        $groupedInteger = strrev(implode('.', str_split(strrev($integer), 3)));
        $isZero = $integer === '0' && (int) $decimals === 0;

        return ($negative && ! $isZero ? '-' : '').$groupedInteger.($scale > 0 ? ','.$decimals : '');
    }
}
