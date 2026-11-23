<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Settings\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-03/C10/C12/C15: anula una compra ya recibida. Solo si ningún lote tiene
 * movimientos aparte de su entrada; registra el movimiento inverso por lote
 * (el kardex es de solo inserción, nunca se edita ni se borra) y deja
 * auditoría inmutable con quién, qué, cuándo y el motivo.
 */
class VoidPurchaseAction
{
    public function execute(Purchase $purchase, string $reason, int $voidedByUserId): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $voidedByUserId) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'received') {
                throw new RuntimeException('Solo se puede anular una compra ya recibida (C12).');
            }

            $lines = $locked->lines()->with('batch.movements')->get();

            foreach ($lines as $line) {
                if ($line->batch->movements->count() > 1) {
                    throw new RuntimeException("El lote {$line->batch->code} ya tiene movimientos; la corrección va por un ajuste de inventario (M4), no por anular la compra.");
                }
            }

            foreach ($lines as $line) {
                $entryMovement = $line->batch->movements->first();

                InventoryMovement::create([
                    'batch_id' => $line->batch_id,
                    'type' => MovementType::PurchaseVoid->value,
                    'quantity' => bcmul((string) $entryMovement->quantity, '-1', 3),
                    'unit_cost' => $entryMovement->unit_cost,
                    'user_id' => $voidedByUserId,
                    'reference_type' => 'purchase_line',
                    'reference_id' => $line->id,
                    'reason' => $reason,
                    'occurred_at' => now(),
                ]);
            }

            $before = ['status' => $locked->status];

            $locked->update([
                'status' => 'voided',
                'voided_by' => $voidedByUserId,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            AuditLog::create([
                'auditable_type' => 'purchase',
                'auditable_id' => $locked->id,
                'action' => 'purchase.void',
                'user_id' => $voidedByUserId,
                'reason' => $reason,
                'before' => $before,
                'after' => ['status' => 'voided'],
            ]);

            return $locked->fresh(['lines.batch']);
        });
    }
}
