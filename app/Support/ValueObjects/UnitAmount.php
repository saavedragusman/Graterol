<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\Formatting\EsVeNumberFormatter;
use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-01: valor UNITARIO (precio o costo por kg) con 4 decimales; no es un
 * monto. Multiplicado por un peso crea un `Money` con el único redondeo
 * explícito al centavo (half-up). No usa tipos de punto flotante (C9).
 */
final class UnitAmount
{
    public const SCALE = 4;

    private readonly Number $value;

    private function __construct(Number $value)
    {
        $this->value = $value;
    }

    /**
     * Valor exacto: acepta ceros sobrantes y lanza InvalidArgumentException
     * si hay más de 4 decimales significativos.
     */
    public static function fromString(string $unit): self
    {
        return new self(DecimalScale::exact($unit, self::SCALE));
    }

    /**
     * Redondeo explícito a 4 decimales (half-up).
     */
    public static function rounded(string|Number $value): self
    {
        return new self(DecimalScale::round($value, self::SCALE));
    }

    /**
     * Costo/precio por kg = total ÷ kg, redondeado half-up a 4 decimales una sola vez.
     */
    public static function fromTotal(Money $total, string $weightKg): self
    {
        $weight = DecimalScale::parse($weightKg);

        if ($weight->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('El peso debe ser mayor a cero para calcular el valor por kg.');
        }

        return self::rounded($total->toNumber()->div($weight, 12));
    }

    public static function zero(): self
    {
        return self::fromString('0');
    }

    /**
     * Valor unitario × peso en kg = monto, redondeado half-up al centavo
     * (el único redondeo del cálculo).
     */
    public function times(string $weightKg): Money
    {
        return Money::rounded($this->value * DecimalScale::parse($weightKg));
    }

    public function compareTo(self $other): int
    {
        return $this->value->compare($other->value);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Texto con 4 decimales exactos ('3.5714'), para guardar.
     */
    public function toStorageString(): string
    {
        return (string) $this->value;
    }

    public function toDisplayString(): string
    {
        return EsVeNumberFormatter::format((string) $this->value, self::SCALE);
    }
}
