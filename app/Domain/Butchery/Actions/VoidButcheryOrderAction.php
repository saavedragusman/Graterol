<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Settings\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-03/C10/C12/C15: anula una orden abierta o cerrada. Solo si ningún lote
 * de salida tiene movimientos aparte de su entrada; revierte el consumo del
 * lote de entrada (butchery_in) y, si ya cerró, cada lote de salida
 * (butchery_out). Kardex de solo inserción: nunca se edita ni se borra, se
 * revierte con un movimiento inverso.
 */
class VoidButcheryOrderAction
{
    public function execute(int $orderId, string $reason, int $voidedByUserId): ButcheryOrder
    {
        return DB::transaction(function () use ($orderId, $reason, $voidedByUserId) {
            $order = ButcheryOrder::with('lines.outputBatch.movements')->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null || ! in_array($order->status, ['open', 'closed'], true)) {
                throw new RuntimeException('Solo se puede anular una orden abierta o cerrada (C12).');
            }

            foreach ($order->lines as $line) {
                $outputBatch = $line->outputBatch;

                if ($outputBatch !== null && $outputBatch->movements->count() > 1) {
                    throw new RuntimeException("El lote {$outputBatch->code} ya tiene movimientos; la corrección va por un ajuste de inventario (M4), no por anular la orden.");
                }
            }

            $entryMovement = InventoryMovement::where('batch_id', $order->batch_id)
                ->where('type', MovementType::ButcheryIn->value)
                ->where('reference_type', 'butchery_order')
                ->where('reference_id', $order->id)
                ->first();

            if ($entryMovement !== null) {
                InventoryMovement::create([
                    'batch_id' => $order->batch_id,
                    'type' => MovementType::ButcheryVoid->value,
                    'quantity' => bcmul((string) $entryMovement->quantity, '-1', 3),
                    'unit_cost' => $entryMovement->unit_cost,
                    'user_id' => $voidedByUserId,
                    'reference_type' => 'butchery_order',
                    'reference_id' => $order->id,
                    'reason' => $reason,
                    'occurred_at' => now(),
                ]);
            }

            foreach ($order->lines as $line) {
                $outputBatch = $line->outputBatch;

                if ($outputBatch === null) {
                    continue;
                }

                $outputMovement = $outputBatch->movements->first();

                InventoryMovement::create([
                    'batch_id' => $outputBatch->id,
                    'type' => MovementType::ButcheryVoid->value,
                    'quantity' => bcmul((string) $outputMovement->quantity, '-1', 3),
                    'unit_cost' => $outputMovement->unit_cost,
                    'user_id' => $voidedByUserId,
                    'reference_type' => 'butchery_order_line',
                    'reference_id' => $line->id,
                    'reason' => $reason,
                    'occurred_at' => now(),
                ]);
            }

            $before = ['status' => $order->status];

            $order->update([
                'status' => 'voided',
                'voided_by' => $voidedByUserId,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            AuditLog::create([
                'auditable_type' => 'butchery_order',
                'auditable_id' => $order->id,
                'action' => 'butchery_order.void',
                'user_id' => $voidedByUserId,
                'reason' => $reason,
                'before' => $before,
                'after' => ['status' => 'voided'],
            ]);

            return $order->fresh(['lines']);
        });
    }
}
