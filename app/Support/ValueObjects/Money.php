<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\Formatting\EsVeNumberFormatter;
use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-01: un MONTO de dinero tiene exactamente 2 decimales y las sumas y restas
 * son exactas (3,99 + 3,99 = 7,98). Nunca redondea por su cuenta: `fromString`
 * rechaza un valor con más de 2 decimales significativos y el único redondeo
 * (half-up, al centavo) es `Money::rounded`, que se usa donde un cálculo crea
 * el monto. Los valores unitarios (precio o costo por kg) son `UnitAmount`.
 * No usa tipos de punto flotante (C9).
 */
final class Money
{
    public const SCALE = 2;

    private readonly Number $value;

    private function __construct(Number $value)
    {
        $this->value = $value;
    }

    /**
     * Monto exacto: acepta ceros sobrantes ('110.0000') y lanza
     * InvalidArgumentException si hay más de 2 decimales significativos.
     */
    public static function fromString(string $amount): self
    {
        return new self(DecimalScale::exact($amount, self::SCALE));
    }

    /**
     * Redondeo explícito al centavo (half-up). Único punto donde un cálculo
     * crea un monto a partir de un valor con más decimales.
     */
    public static function rounded(string|Number $value): self
    {
        return new self(DecimalScale::round($value, self::SCALE));
    }

    public static function zero(): self
    {
        return self::fromString('0');
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function sub(self $other): self
    {
        return new self($this->value - $other->value);
    }

    /**
     * Producto exacto, sin redondear (más de 2 decimales posibles). Para
     * convertirlo en monto hay que llamar a `Money::rounded` explícitamente.
     */
    public function mul(string $factor): Number
    {
        return $this->value * DecimalScale::parse($factor);
    }

    /**
     * Cociente exacto a `$scale` decimales (por defecto 10, truncado), sin
     * redondeo de monto. Redondear con `Money::rounded` una sola vez.
     */
    public function div(string $divisor, int $scale = 10): Number
    {
        $divisorNumber = DecimalScale::parse($divisor);

        if ($divisorNumber->compare(new Number('0')) === 0) {
            throw new InvalidArgumentException('No se puede dividir un monto entre cero.');
        }

        return $this->value->div($divisorNumber, $scale);
    }

    public function toNumber(): Number
    {
        return $this->value;
    }

    public function compareTo(self $other): int
    {
        return $this->value->compare($other->value);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isNegative(): bool
    {
        return $this->value->compare(new Number('0')) < 0;
    }

    /**
     * Texto con 2 decimales exactos ('7.98'), listo para guardar o comparar.
     */
    public function toInternalString(): string
    {
        return (string) $this->value;
    }

    public function toDisplayString(): string
    {
        return EsVeNumberFormatter::format((string) $this->value, self::SCALE);
    }

    /**
     * Monto listo para pantalla: 2 decimales es-VE y siempre con su moneda
     * («Bs 1.234,50» / «$ 1.234,50»). Acepta el valor tal como viene de la
     * base (columnas aún con 4 decimales o precios unitarios): para mostrarlo
     * se redondea half-up una sola vez a 2 decimales.
     */
    public static function display(?string $amount, string $currency): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return self::label($currency).' '.self::rounded($amount)->toDisplayString();
    }

    /**
     * Doble monto de pantalla (design.md §3 MoneyAmount): divisa ($) arriba y
     * Bs debajo con la tasa usada. La conversión se hace sobre el total y se
     * redondea UNA vez, sin pasos intermedios: USD → Bs = round(total × tasa);
     * VES → USD = round(total ÷ tasa). Sin tasa (null, vacía o cero) solo se
     * devuelve el lado de la moneda original: la tasa nunca se inventa. La
     * tasa es Bs por USD.
     *
     * @return array{usd: ?string, bs: ?string, rate: ?string}
     */
    public static function dualDisplay(?string $amount, string $currency, ?string $rate): array
    {
        if ($amount === null || $amount === '') {
            return ['usd' => null, 'bs' => null, 'rate' => null];
        }

        $isUsd = strtoupper($currency) === 'USD';
        $original = self::display($amount, $currency);

        $hasRate = $rate !== null && $rate !== '' && (new Number($rate))->compare(new Number('0')) > 0;

        if (! $hasRate) {
            return ['usd' => $isUsd ? $original : null, 'bs' => $isUsd ? null : $original, 'rate' => null];
        }

        $value = DecimalScale::parse($amount);
        $rateNumber = new Number($rate);

        if ($isUsd) {
            $converted = self::rounded($value * $rateNumber);

            return ['usd' => $original, 'bs' => self::label('VES').' '.$converted->toDisplayString(), 'rate' => self::display($rate, 'VES')];
        }

        $converted = self::rounded($value->div($rateNumber, 10));

        return ['usd' => self::label('USD').' '.$converted->toDisplayString(), 'bs' => $original, 'rate' => self::display($rate, 'VES')];
    }

    /**
     * Las dos líneas de pantalla del doble monto: principal en divisa ($) y
     * secundaria en Bs con la tasa. Sin tasa y en Bs, el principal es el Bs
     * original y no hay secundaria.
     *
     * @return array{main: ?string, secondary: ?string}
     */
    public static function dualLines(?string $amount, string $currency, ?string $rate): array
    {
        $dual = self::dualDisplay($amount, $currency, $rate);

        if ($dual['usd'] === null) {
            return ['main' => $dual['bs'], 'secondary' => null];
        }

        return ['main' => $dual['usd'], 'secondary' => $dual['bs']];
    }

    private static function label(string $currency): string
    {
        return match (strtoupper($currency)) {
            'VES', 'BS' => 'Bs',
            'USD' => '$',
            default => strtoupper($currency),
        };
    }
}
