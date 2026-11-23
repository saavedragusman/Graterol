<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryCount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-16/C12: descarta un conteo en borrador (`draft` → `cancelled`). No escribe movimientos.
 * Una sesión confirmada o ya descartada no se toca.
 */
class CancelInventoryCountAction
{
    public function execute(int $countId): InventoryCount
    {
        return DB::transaction(function () use ($countId) {
            $count = InventoryCount::query()->whereKey($countId)->lockForUpdate()->firstOrFail();

            if ($count->status !== 'draft') {
                throw new RuntimeException("El conteo {$count->code} ya no es un borrador: no se puede descartar.");
            }

            $count->update(['status' => 'cancelled']);

            return $count;
        });
    }
}
