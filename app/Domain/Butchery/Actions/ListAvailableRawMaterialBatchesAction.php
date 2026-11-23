<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Catalog\ProductType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
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
        $rawMaterialBatchIds = Batch::query()
            ->whereHas('product', fn ($query) => $query->where('product_type', ProductType::RawMaterial->value))
            ->pluck('id');

        $balances = InventoryMovement::query()
            ->whereIn('batch_id', $rawMaterialBatchIds)
            ->selectRaw('batch_id, SUM(quantity) as balance')
            ->groupBy('batch_id')
            ->pluck('balance', 'batch_id');

        return Batch::query()
            ->whereIn('id', $rawMaterialBatchIds)
            ->with('product')
            ->get()
            ->filter(fn (Batch $batch) => bccomp((string) ($balances[$batch->id] ?? '0'), '0', 3) > 0)
            ->each(fn (Batch $batch) => $batch->setAttribute('balance', (string) $balances[$batch->id]))
            ->values();
    }
}
