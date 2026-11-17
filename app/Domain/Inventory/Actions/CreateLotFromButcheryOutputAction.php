<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BR-12/C10: cada corte con peso real > 0 nace como su propio lote, con el
 * costo asignado por el reparto de valor relativo de mercado, y su
 * movimiento `butchery_out` en el kardex de solo inserción. Análoga a
 * `CreateLotFromPurchaseLineAction` (M2), pero el origen es una línea de
 * orden de despiece, no una línea de compra.
 */
class CreateLotFromButcheryOutputAction
{
    public function execute(
        int $sourceId,
        int $productId,
        string $weightKg,
        string $totalCost,
        string $currency,
        string $exchangeRate,
        int $userId,
    ): Batch {
        if ((new Number($weightKg))->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('Solo se crea un lote de salida para un corte con peso real mayor a cero (BR-12).');
        }

        $unitCost = (string) (new Number($totalCost))->div(new Number($weightKg), 4);

        return DB::transaction(function () use ($sourceId, $productId, $weightKg, $totalCost, $currency, $exchangeRate, $userId, $unitCost) {
            $batch = Batch::create([
                'code' => "butchery_order_line-{$sourceId}",
                'product_id' => $productId,
                'source_type' => 'butchery_order_line',
                'source_id' => $sourceId,
                'unit' => 'kg',
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'received_at' => now(),
            ]);

            InventoryMovement::create([
                'batch_id' => $batch->id,
                'type' => 'butchery_out',
                'quantity' => $weightKg,
                'unit_cost' => $unitCost,
                'user_id' => $userId,
                'reference_type' => 'butchery_order_line',
                'reference_id' => $sourceId,
                'occurred_at' => now(),
            ]);

            return $batch;
        });
    }
}
