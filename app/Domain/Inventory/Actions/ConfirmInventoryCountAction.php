<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Inventory\StockExitWriter;
use App\Domain\Settings\Models\AuditLog;
use App\Support\ValueObjects\DecimalScale;
use App\Support\ValueObjects\Money;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-15/BR-16/C15: confirma un conteo físico y genera un ajuste por cada diferencia ≠ 0.
 *
 * - Una sola transacción (C11): bloquea la sesión y los lotes de sus líneas (en orden de id).
 *   Exige sesión `draft` y todas las líneas pesadas; si algo falla no se escribe nada.
 * - Diferencia negativa (DEC-07 A): salida costeada con `StockExitWriter` (kg × costo unitario al
 *   centavo; si deja el lote en 0,000 kg toma exactamente el valor restante, C9). Monto negativo.
 * - Diferencia positiva (DEC-11 B): entra con el costo unitario del lote y monto 0,00; no suma valor.
 * - Se ajusta por `difference_kg` guardada al pesar (texto literal de DEC-07). Si desde entonces el
 *   saldo bajó y la salida lo dejaría negativo, se rechaza toda la confirmación (el documento no
 *   define esta regla; es una decisión pendiente del humano).
 * - `user_id` = quien confirma; `responsible_user_id` = el responsable de la línea (DEC-05).
 * - Una fila de `audit_logs` por ajuste (C15). La sesión confirmada no se edita más (C12).
 */
class ConfirmInventoryCountAction
{
    public function __construct(
        private readonly StockBalance $stock = new StockBalance,
        private readonly StockExitWriter $exits = new StockExitWriter,
    ) {}

    public function execute(int $countId, int $userId): InventoryCount
    {
        return DB::transaction(function () use ($countId, $userId) {
            $count = InventoryCount::query()->whereKey($countId)->lockForUpdate()->firstOrFail();

            if ($count->status !== 'draft') {
                throw new RuntimeException("El conteo {$count->code} ya no es un borrador: no se puede confirmar.");
            }

            $lines = $count->lines()->orderBy('batch_id')->get();

            if ($lines->isEmpty() || $lines->contains(fn ($line) => $line->counted_weight_kg === null || $line->difference_kg === null)) {
                throw new RuntimeException("El conteo {$count->code} tiene líneas sin pesar: pese todos los lotes antes de confirmar.");
            }

            $batches = Batch::query()
                ->whereIn('id', $lines->pluck('batch_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $zero = new Number('0');
            $reason = "Ajuste por conteo físico {$count->code}";

            foreach ($lines as $line) {
                $difference = new Number((string) $line->difference_kg);

                if ($difference->compare($zero) === 0) {
                    continue;
                }

                $batch = $batches[$line->batch_id];
                $balance = new Number($this->stock->forBatchLocked($batch->id));
                $valueBefore = $this->stock->valueForBatchLocked($batch->id);
                $responsibleId = (int) $line->responsible_user_id;

                if ($difference->compare($zero) < 0) {
                    $kg = $zero - $difference;

                    if ($kg->compare($balance) > 0) {
                        throw new RuntimeException(
                            "El ajuste del lote {$batch->code} ({$kg} kg) dejaría el saldo negativo: el saldo actual es {$balance} kg. Corrija el conteo con una sesión nueva."
                        );
                    }

                    $movement = $this->exits->write(
                        $batch, $kg, $this->exits->costOf($batch, $kg, $balance),
                        MovementType::CountAdjustment,
                        referenceType: 'inventory_count', referenceId: $count->id,
                        userId: $userId, reason: $reason, responsibleUserId: $responsibleId,
                    );
                } else {
                    $movement = InventoryMovement::create([
                        'batch_id' => $batch->id,
                        'type' => MovementType::CountAdjustment->value,
                        'quantity' => (string) DecimalScale::round($difference, 3),
                        'unit_cost' => $batch->unit_cost,
                        'total_cost' => Money::zero()->toInternalString(),
                        'user_id' => $userId,
                        'responsible_user_id' => $responsibleId,
                        'reference_type' => 'inventory_count',
                        'reference_id' => $count->id,
                        'reason' => $reason,
                        'occurred_at' => now(),
                    ]);
                }

                $line->update(['adjustment_movement_id' => $movement->id]);

                AuditLog::create([
                    'auditable_type' => 'batch',
                    'auditable_id' => $batch->id,
                    'action' => 'inventory.count_adjusted',
                    'user_id' => $userId,
                    'reason' => $reason,
                    'before' => [
                        'balance_kg' => (string) $balance,
                        'value' => $valueBefore->toInternalString(),
                        'type' => MovementType::CountAdjustment->value,
                        'responsible_user_id' => $responsibleId,
                    ],
                    'after' => [
                        'balance_kg' => $this->stock->forBatchLocked($batch->id),
                        'value' => $this->stock->valueForBatchLocked($batch->id)->toInternalString(),
                        'type' => MovementType::CountAdjustment->value,
                        'responsible_user_id' => $responsibleId,
                    ],
                ]);
            }

            $count->update([
                'status' => 'confirmed',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return $count;
        });
    }
}
