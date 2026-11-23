<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountLine;
use App\Domain\Inventory\Queries\StockBalance;
use App\Models\User;
use App\Support\ValueObjects\DecimalScale;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * BR-16/DEC-07/DEC-05: guarda el peso contado de una línea con su responsable.
 *
 * - Solo mientras la sesión está en `draft`; volver a pesar sobrescribe el peso anterior.
 * - Peso con 3 decimales exactos y >= 0 (0 es válido: el lote no apareció). Responsable obligatorio:
 *   usuario existente y activo, puede ser distinto de quien teclea (`counted_by`).
 * - Una sola transacción (C11): bloquea la sesión y el lote y lee el saldo con `forBatchLocked`,
 *   así las ventas posteriores no deforman la diferencia = contado − saldo del sistema.
 */
class RegisterCountedWeightAction
{
    public function __construct(private readonly StockBalance $stock = new StockBalance) {}

    public function execute(int $lineId, string $countedKg, ?int $responsibleUserId, int $userId): InventoryCountLine
    {
        $counted = DecimalScale::exact($countedKg, 3);

        if ($counted->compare(new Number('0')) < 0) {
            throw new InvalidArgumentException('El peso contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($lineId, $counted, $responsibleUserId, $userId) {
            if ($responsibleUserId === null || ! User::query()->whereKey($responsibleUserId)->where('is_active', true)->exists()) {
                throw new InvalidArgumentException('El responsable debe ser un usuario existente y activo.');
            }

            $line = InventoryCountLine::query()->findOrFail($lineId);

            $count = InventoryCount::query()->whereKey($line->count_id)->lockForUpdate()->firstOrFail();

            if ($count->status !== 'draft') {
                throw new RuntimeException("El conteo {$count->code} ya no es un borrador: no se puede pesar.");
            }

            $batch = Batch::query()->whereKey($line->batch_id)->lockForUpdate()->firstOrFail();

            $system = new Number($this->stock->forBatchLocked($batch->id));

            $line->update([
                'system_quantity_kg' => (string) $system,
                'counted_weight_kg' => (string) $counted,
                'difference_kg' => (string) DecimalScale::round($counted - $system, 3),
                'counted_by' => $userId,
                'counted_at' => now(),
                'responsible_user_id' => $responsibleUserId,
            ]);

            return $line->refresh();
        });
    }
}
