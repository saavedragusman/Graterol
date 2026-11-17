<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Support\ValueObjects\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-06/BR-07: toda recepción se pesa; el costo del lote = compra + flete +
 * otros costos asignados, y el lote nace aquí, al confirmar. Una recepción
 * por compra (DEC-01); todo en una transacción (C11).
 */
class ReceivePurchaseAction
{
    /**
     * @param  array<int, array{received_weight_kg: string, received_pieces?: int|null, expires_on?: CarbonInterface|null}>  $receivedByLine  indexado por purchase_line id
     */
    public function execute(Purchase $purchase, array $receivedByLine, int $receivedByUserId): Purchase
    {
        return DB::transaction(function () use ($purchase, $receivedByLine, $receivedByUserId) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'draft') {
                throw new RuntimeException('Solo se puede recibir una compra en borrador (C12).');
            }

            $lines = $locked->lines;

            foreach ($lines as $line) {
                if (! array_key_exists($line->id, $receivedByLine)) {
                    throw new RuntimeException("La línea {$line->id} no tiene peso recibido; toda recepción se pesa (BR-06).");
                }
            }

            $weightsByLine = [];
            foreach ($lines as $line) {
                $weightsByLine[$line->id] = $receivedByLine[$line->id]['received_weight_kg'];
            }

            $allocatedFreight = (new AllocateCostsAction)->execute((string) $locked->freight_amount, $weightsByLine);
            $allocatedOtherCosts = (new AllocateCostsAction)->execute((string) $locked->other_costs_amount, $weightsByLine);

            foreach ($lines as $line) {
                $receivedWeight = $receivedByLine[$line->id]['received_weight_kg'];
                $receivedPieces = $receivedByLine[$line->id]['received_pieces'] ?? null;
                $expiresOn = $receivedByLine[$line->id]['expires_on'] ?? null;

                $totalCost = Money::fromString((string) $line->line_amount)
                    ->add(Money::fromString($allocatedFreight[$line->id]))
                    ->add(Money::fromString($allocatedOtherCosts[$line->id]));

                $batch = (new CreateLotFromPurchaseLineAction)->execute(
                    sourceType: 'purchase_line',
                    sourceId: $line->id,
                    productId: $line->product_id,
                    pieces: $receivedPieces,
                    receivedWeightKg: $receivedWeight,
                    totalCost: $totalCost->toInternalString(),
                    currency: $locked->currency,
                    exchangeRate: (string) $locked->exchange_rate,
                    userId: $receivedByUserId,
                    expiresOn: $expiresOn,
                );

                $line->update([
                    'received_weight_kg' => $receivedWeight,
                    'received_pieces' => $receivedPieces,
                    'weight_difference_kg' => bcsub($receivedWeight, (string) $line->invoiced_weight_kg, 3),
                    'allocated_freight' => $allocatedFreight[$line->id],
                    'allocated_other_costs' => $allocatedOtherCosts[$line->id],
                    'expires_on' => $expiresOn,
                    'batch_id' => $batch->id,
                ]);
            }

            $locked->update([
                'status' => 'received',
                'received_by' => $receivedByUserId,
                'received_at' => now(),
            ]);

            return $locked->fresh(['lines.batch']);
        });
    }
}
