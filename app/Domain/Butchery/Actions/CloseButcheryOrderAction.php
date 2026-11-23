<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\PriceList;
use App\Domain\Inventory\Actions\CreateLotFromButcheryOutputAction;
use App\Support\ValueObjects\Money;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-10/BR-11/BR-12: cierra la orden. Valida que toda línea tenga peso real
 * registrado y que las salidas no superen la entrada (BR-10); costea por
 * valor relativo de mercado sobre el costo de ESTA entrada (no el lote
 * completo, DEC-07), con el precio detal vigente (DEC-01); calcula la
 * desviación frente a la plantilla (BR-11); crea un lote de salida por
 * corte con peso real > 0 (BR-12, C10).
 */
class CloseButcheryOrderAction
{
    public function execute(int $orderId, int $closedByUserId): ButcheryOrder
    {
        return DB::transaction(function () use ($orderId, $closedByUserId) {
            $order = ButcheryOrder::with(['lines', 'batch'])->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null || $order->status !== 'open') {
                throw new RuntimeException('Solo se puede cerrar una orden abierta (C12).');
            }

            $lines = $order->lines;

            foreach ($lines as $line) {
                if ($line->actual_weight_kg === null) {
                    throw new RuntimeException("El corte {$line->cut_product_id} todavía no tiene peso real registrado (BR-10).");
                }
            }

            $totalOutput = new Number('0');
            foreach ($lines as $line) {
                $totalOutput = $totalOutput->add(new Number((string) $line->actual_weight_kg));
            }

            $inputWeight = new Number((string) $order->input_weight_kg);

            if ($totalOutput->compare($inputWeight) > 0) {
                throw new RuntimeException('Las salidas no pueden superar los kg de entrada (BR-10).');
            }

            $cutsByKey = [];
            foreach ($lines as $line) {
                $weight = new Number((string) $line->actual_weight_kg);

                if ($weight->compare(new Number('0')) <= 0) {
                    $cutsByKey[$line->id] = ['weight_kg' => '0.000', 'price_per_kg' => '0'];

                    continue;
                }

                $price = Product::find($line->cut_product_id)?->priceFor(PriceList::Detail->value);

                if ($price === null) {
                    throw new RuntimeException("El corte {$line->cut_product_id} no tiene precio de lista detal configurado; no se puede costear (DEC-01).");
                }

                $cutsByKey[$line->id] = [
                    'weight_kg' => (string) $line->actual_weight_kg,
                    'price_per_kg' => (string) $price->amount,
                ];
            }

            $batch = $order->batch;
            $orderInputCost = Money::fromString((string) $batch->unit_cost)->mul((string) $order->input_weight_kg);

            $allocated = (new AllocateButcheryCostsAction)->execute($orderInputCost->toInternalString(), $cutsByKey);

            foreach ($lines as $line) {
                $weight = new Number((string) $line->actual_weight_kg);
                $deviation = $weight->div($inputWeight, 10)
                    ->mul(new Number('100'))
                    ->sub(new Number((string) $line->expected_yield_percent))
                    ->round(2);

                $outputBatchId = null;

                if ($weight->compare(new Number('0')) > 0) {
                    $outputBatch = (new CreateLotFromButcheryOutputAction)->execute(
                        sourceId: $line->id,
                        productId: $line->cut_product_id,
                        weightKg: (string) $line->actual_weight_kg,
                        totalCost: $allocated[$line->id],
                        currency: (string) $batch->currency,
                        exchangeRate: (string) $batch->exchange_rate,
                        userId: $closedByUserId,
                    );
                    $outputBatchId = $outputBatch->id;
                }

                $line->update([
                    'reference_price_per_kg' => $cutsByKey[$line->id]['price_per_kg'] === '0' ? null : $cutsByKey[$line->id]['price_per_kg'],
                    'allocated_cost' => $allocated[$line->id],
                    'deviation_percent' => (string) $deviation,
                    'output_batch_id' => $outputBatchId,
                ]);
            }

            $order->update([
                'status' => 'closed',
                'actual_shrinkage_kg' => (string) $inputWeight->sub($totalOutput),
                'closed_at' => now(),
            ]);

            return $order->fresh(['lines']);
        });
    }
}
