<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * BR-09: abre la orden con el lote de entrada, los kg de entrada y el
 * carnicero; congela la plantilla activa (DEC-03) y muestra los kg
 * esperados por corte. DEC-04: consume el lote (butchery_in) al abrir, con
 * bloqueo de fila, para que dos órdenes no sobre-consuman el mismo lote.
 * DEC-07: puede tomar solo parte del saldo disponible del lote.
 */
class OpenButcheryOrderAction
{
    public function execute(int $batchId, string $inputWeightKg, int $butcherId): ButcheryOrder
    {
        if ((new Number($inputWeightKg))->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('Los kg de entrada deben ser mayores a cero (BR-09).');
        }

        return DB::transaction(function () use ($batchId, $inputWeightKg, $butcherId) {
            $batch = Batch::whereKey($batchId)->lockForUpdate()->first();

            if ($batch === null) {
                throw new RuntimeException('El lote no existe.');
            }

            $isRawMaterial = $batch->product_id !== null
                && Product::whereKey($batch->product_id)
                    ->where('product_type', ProductType::RawMaterial->value)
                    ->exists();

            if (! $isRawMaterial) {
                throw new InvalidArgumentException('Solo se puede despiezar un lote de materia prima (BR-04/BR-08).');
            }

            $template = ButcheryTemplate::with('lines')
                ->where('raw_material_product_id', $batch->product_id)
                ->where('is_active', true)
                ->first();

            if ($template === null) {
                throw new RuntimeException('No hay una plantilla activa para esta materia prima (BR-08).');
            }

            $available = $this->availableBalance($batch->id);
            $input = new Number($inputWeightKg);

            if ($input->compare($available) > 0) {
                throw new RuntimeException("Los kg de entrada ({$inputWeightKg}) superan el saldo disponible del lote ({$available}) (DEC-07).");
            }

            $order = ButcheryOrder::create([
                'code' => Str::random(20),
                'batch_id' => $batch->id,
                'template_id' => $template->id,
                'input_weight_kg' => $inputWeightKg,
                'expected_shrinkage_percent' => $template->expected_shrinkage_percent,
                'butcher_id' => $butcherId,
                'status' => 'open',
            ]);

            $order->update(['code' => sprintf('D-%05d', $order->id)]);

            foreach ($template->lines as $line) {
                $expectedWeight = $input->mul(new Number((string) $line->expected_yield_percent))->div(new Number('100'), 3);

                $order->lines()->create([
                    'cut_product_id' => $line->cut_product_id,
                    'expected_yield_percent' => $line->expected_yield_percent,
                    'expected_weight_kg' => (string) $expectedWeight,
                ]);
            }

            InventoryMovement::create([
                'batch_id' => $batch->id,
                'type' => MovementType::ButcheryIn->value,
                'quantity' => (string) $input->mul(new Number('-1')),
                'unit_cost' => $batch->unit_cost,
                'user_id' => $butcherId,
                'reference_type' => 'butchery_order',
                'reference_id' => $order->id,
                'occurred_at' => now(),
            ]);

            return $order->load('lines');
        });
    }

    private function availableBalance(int $batchId): Number
    {
        $balance = new Number('0');

        foreach (InventoryMovement::where('batch_id', $batchId)->get(['quantity']) as $movement) {
            $balance = $balance->add(new Number((string) $movement->quantity));
        }

        return $balance;
    }
}
