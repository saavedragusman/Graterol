<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Queries;

use App\Domain\Catalog\ProductType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;
use BcMath\Number;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * BR-14: única fuente de saldo del código. Stock = Σ inventory_movements.quantity
 * por lote; nunca se guarda un saldo denormalizado (C4). Todos los saldos salen
 * como string con 3 decimales; la aritmética es con BcMath\Number, sin decimales binarios (C9).
 */
class StockBalance
{
    private const ZERO = '0.000';

    /**
     * Saldo de un lote: Σ de todos sus movimientos (entradas, salidas, mermas y anulaciones).
     */
    public function forBatch(int $batchId): string
    {
        return $this->normalize(
            InventoryMovement::query()->where('batch_id', $batchId)->sum('quantity')
        );
    }

    /**
     * Saldo de un producto: Σ del saldo de todos sus lotes.
     */
    public function forProduct(int $productId): string
    {
        $total = InventoryMovement::query()
            ->whereIn('batch_id', Batch::query()->where('product_id', $productId)->select('id'))
            ->sum('quantity');

        return $this->normalize($total);
    }

    /**
     * Saldo de un lote para usar dentro de una transacción.
     *
     * El llamador (FEFO, merma, conteo) ya tomó `lockForUpdate` sobre las filas de
     * `batches`; esto relee el saldo desde la base de datos dentro de esa misma
     * transacción, así que ve los movimientos confirmados hasta ese momento y
     * ninguno nuevo puede colarse mientras el bloqueo siga vigente (los demás
     * escritores esperan el bloqueo del lote). No guarda nada en memoria.
     */
    public function forBatchLocked(int $batchId): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('forBatchLocked() debe llamarse dentro de una transacción con el lote bloqueado (BR-14).');
        }

        return $this->forBatch($batchId);
    }

    /**
     * Valor de un lote (C9): `batches.total_cost` + Σ `total_cost` con signo de sus
     * movimientos que no son la entrada inicial (`purchase_in` o `butchery_out`).
     * Un movimiento sin `total_cost` (anterior a la columna) se valora con
     * round(quantity × unit_cost) al centavo, half-up. Nunca es saldo × unit_cost.
     */
    public function valueForBatch(int $batchId): Money
    {
        $total = Batch::query()->whereKey($batchId)->value('total_cost');
        $value = Money::fromString($total === null ? '0' : (string) $total);

        $movements = InventoryMovement::query()
            ->where('batch_id', $batchId)
            ->whereNotIn('type', [MovementType::PurchaseIn->value, MovementType::ButcheryOut->value])
            ->get(['quantity', 'unit_cost', 'total_cost']);

        foreach ($movements as $movement) {
            $amount = $movement->total_cost !== null
                ? Money::fromString((string) $movement->total_cost)
                : UnitAmount::fromString((string) $movement->unit_cost)->times((string) $movement->quantity);

            $value = $value->add($amount);
        }

        return $value;
    }

    /**
     * Valor del lote para usar dentro de una transacción con el lote bloqueado (ver forBatchLocked).
     */
    public function valueForBatchLocked(int $batchId): Money
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('valueForBatchLocked() debe llamarse dentro de una transacción con el lote bloqueado (C11).');
        }

        return $this->valueForBatch($batchId);
    }

    /**
     * Lotes activos (saldo > 0) en una sola consulta agregada sobre
     * inventory_movements(batch_id), con `balance` como atributo string de 3 decimales.
     *
     * @return Collection<int, Batch>
     */
    public function activeBatches(?int $productId = null, ?ProductType $productType = null): Collection
    {
        $balances = InventoryMovement::query()
            ->selectRaw('batch_id, SUM(quantity) as balance')
            ->groupBy('batch_id');

        $query = Batch::query()
            ->joinSub($balances, 'balances', 'balances.batch_id', '=', 'batches.id')
            ->where('balances.balance', '>', 0)
            ->select('batches.*', 'balances.balance')
            ->with('product')
            ->orderBy('batches.id');

        if ($productId !== null) {
            $query->where('batches.product_id', $productId);
        }

        if ($productType !== null) {
            $query->whereHas('product', fn ($product) => $product->where('product_type', $productType->value));
        }

        return $query->get()->each(
            fn (Batch $batch) => $batch->setAttribute('balance', $this->normalize($batch->getAttribute('balance')))
        );
    }

    /**
     * Fija el formato de salida: string con 3 decimales ('0.000' si no hay movimientos).
     */
    private function normalize(mixed $sum): string
    {
        if ($sum === null || $sum === '') {
            return self::ZERO;
        }

        return (string) (new Number((string) $sum))->round(3);
    }
}
