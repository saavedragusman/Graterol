<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\Batch;
use App\Support\ValueObjects\Money;

/**
 * BR-14: lo que una salida FEFO tomó de un lote (un movimiento del kardex).
 * `kg` es positivo con 3 decimales y `cost` es el monto positivo de esa parte.
 */
final readonly class FefoAllocation
{
    public function __construct(
        public Batch $batch,
        public string $kg,
        public Money $cost,
    ) {}
}
