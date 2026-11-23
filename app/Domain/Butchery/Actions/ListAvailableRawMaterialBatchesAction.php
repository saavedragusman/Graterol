<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Catalog\ProductType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Queries\StockBalance;
use Illuminate\Support\Collection;

/**
 * BR-09/DEC-07: lotes de materia prima con saldo disponible (Σ movimientos
 * > 0) para abrir una nueva orden de despiece.
 */
class ListAvailableRawMaterialBatchesAction
{
    /**
     * @return Collection<int, Batch>
     */
    public function execute(): Collection
    {
        return (new StockBalance)->activeBatches(productType: ProductType::RawMaterial);
    }
}
