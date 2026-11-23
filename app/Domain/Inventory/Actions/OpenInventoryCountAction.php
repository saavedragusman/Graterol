<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Queries\StockBalance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BR-16/DEC-07 A: abre una sesión de conteo físico en `draft` con una línea por lote elegido.
 *
 * - Sin lotes indicados, cuenta todos los lotes con saldo > 0 (`StockBalance::activeBatches`).
 * - Rechaza una selección vacía y lotes inexistentes; un lote repetido genera una sola línea.
 * - El código `C-0007` es el siguiente número consecutivo, calculado bajo un candado asesor de
 *   PostgreSQL de la transacción: dos sesiones abiertas a la vez nunca comparten código y no
 *   quedan huecos si una apertura falla.
 * - La autorización es de la Policy de la pantalla, no de esta Action.
 */
class OpenInventoryCountAction
{
    /** Llave del candado asesor de PostgreSQL que serializa la numeración de conteos. */
    private const CODE_LOCK = 4160001;

    public function __construct(private readonly StockBalance $stock = new StockBalance) {}

    /**
     * @param  list<int>  $batchIds
     */
    public function execute(array $batchIds, int $userId, ?string $notes = null): InventoryCount
    {
        $ids = array_values(array_unique(array_map('intval', $batchIds)));

        if ($ids === []) {
            $ids = $this->stock->activeBatches()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($ids === []) {
            throw new InvalidArgumentException('No hay lotes para contar: elija al menos un lote.');
        }

        return DB::transaction(function () use ($ids, $userId, $notes) {
            $found = Batch::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (count($found) !== count($ids)) {
                throw new InvalidArgumentException('Alguno de los lotes elegidos no existe.');
            }

            sort($ids);

            $notes = trim((string) $notes);

            // Serializa la numeración: el candado dura hasta el fin de la transacción.
            DB::select('select pg_advisory_xact_lock(?)', [self::CODE_LOCK]);

            $last = (int) InventoryCount::query()
                ->selectRaw('max(cast(substring(code from 3) as bigint)) as last_number')
                ->where('code', 'like', 'C-%')
                ->value('last_number');

            $count = InventoryCount::create([
                'code' => sprintf('C-%04d', $last + 1),
                'status' => 'draft',
                'notes' => $notes === '' ? null : $notes,
                'created_by' => $userId,
            ]);

            foreach ($ids as $batchId) {
                $count->lines()->create(['batch_id' => $batchId]);
            }

            return $count;
        });
    }
}
