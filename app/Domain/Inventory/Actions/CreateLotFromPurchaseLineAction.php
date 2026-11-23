<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * BR-07/C10: el lote nace en la recepción de una compra, con su costo total
 * (monto facturado + costos asignados) y el movimiento de entrada en el
 * kardex de solo inserción. No conoce `Purchasing` (C4): recibe datos
 * genéricos, igual que `source_type`/`source_id` en `batches`.
 */
class CreateLotFromPurchaseLineAction
{
    public function execute(
        string $sourceType,
        int $sourceId,
        int $productId,
        ?int $pieces,
        string $receivedWeightKg,
        string $totalCost,
        string $currency,
        string $exchangeRate,
        int $userId,
        ?CarbonInterface $expiresOn = null,
    ): Batch {
        // El total es un monto exacto de 2 decimales; el costo por kg es unitario (4 decimales).
        $total = Money::fromString($totalCost);
        $totalCost = $total->toInternalString();
        $unitCost = UnitAmount::fromTotal($total, $receivedWeightKg)->toStorageString();

        return DB::transaction(function () use ($sourceType, $sourceId, $productId, $pieces, $receivedWeightKg, $totalCost, $currency, $exchangeRate, $userId, $expiresOn, $unitCost) {
            $batch = Batch::create([
                'code' => "{$sourceType}-{$sourceId}",
                'product_id' => $productId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'unit' => 'kg',
                'pieces' => $pieces,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'received_at' => now(),
                'expires_at' => $expiresOn,
            ]);

            InventoryMovement::create([
                'batch_id' => $batch->id,
                'type' => MovementType::PurchaseIn->value,
                'quantity' => $receivedWeightKg,
                'unit_cost' => $unitCost,
                'user_id' => $userId,
                'reference_type' => $sourceType,
                'reference_id' => $sourceId,
                'occurred_at' => now(),
            ]);

            return $batch;
        });
    }
}
