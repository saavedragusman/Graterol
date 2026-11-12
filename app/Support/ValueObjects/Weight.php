<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\Formatting\EsVeNumberFormatter;
use BcMath\Number;

/**
 * BR-01: peso en kg con 3 decimales. No usa tipos de punto flotante (C9).
 */
final class Weight
{
    public const SCALE = 3;

    private readonly Number $value;

    private function __construct(Number $value)
    {
        $this->value = $value->round(self::SCALE);
    }

    public static function fromString(string $kg): self
    {
        return new self(new Number($kg));
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

    public function toStorageString(): string
    {
        return (string) $this->value;
    }

    public function toDisplayString(): string
    {
        return EsVeNumberFormatter::format((string) $this->value, self::SCALE);
    }
}
