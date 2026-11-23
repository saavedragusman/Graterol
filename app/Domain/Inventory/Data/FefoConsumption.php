<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Data;

use App\Support\ValueObjects\Money;

/**
 * Resultado de una salida FEFO: las asignaciones por lote y el costo total.
 */
final readonly class FefoConsumption
{
    /**
     * @param  list<FefoAllocation>  $allocations
     */
    public function __construct(
        public array $allocations,
        public Money $totalCost,
    ) {}
}
