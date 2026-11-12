<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\Formatting\EsVeNumberFormatter;
use BcMath\Number;
use RoundingMode;

/**
 * BR-01: dinero con 4 decimales internos, 2 en pantalla/impresión. No usa tipos de punto flotante (C9).
 */
final class Money
{
    public const INTERNAL_SCALE = 4;

    public const DISPLAY_SCALE = 2;

    private readonly Number $value;

    private function __construct(Number $value)
    {
        $this->value = $value->round(self::INTERNAL_SCALE);
    }

    public static function fromString(string $amount): self
    {
        return new self(new Number($amount));
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

    public function mul(string $factor): self
    {
        return new self($this->value * new Number($factor));
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

    public function toInternalString(): string
    {
        return (string) $this->value;
    }

    public function toDisplayString(): string
    {
        $rounded = $this->value->round(self::DISPLAY_SCALE, RoundingMode::HalfAwayFromZero);

        return EsVeNumberFormatter::format((string) $rounded, self::DISPLAY_SCALE);
    }
}
