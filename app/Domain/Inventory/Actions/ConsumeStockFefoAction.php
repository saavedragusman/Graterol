<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Data\FefoAllocation;
use App\Domain\Inventory\Data\FefoConsumption;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Inventory\StockExitWriter;
use App\Domain\Settings\Models\Setting;
use App\Support\ValueObjects\DecimalScale;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * BR-14/BR-17: salida de stock de un producto por FEFO, con el costo exacto de cada lote (C9).
 *
 * - Una sola transacción (C11): bloquea los lotes del producto en orden de id y relee los saldos.
 * - Solo se consumen lotes con saldo > 0 y no vencidos (DEC-03 a B); la fecha de vencimiento se
 *   compara como fecha en UTC, sin convertir a Caracas.
 * - Orden: vence antes primero; sin fecha al final (DEC-02 A); luego recepción y luego id.
 * - Cada asignación escribe un movimiento negativo con `total_cost` negativo: kg × costo del lote
 *   redondeado al centavo, salvo la que deja el lote en 0,000 kg, que toma exactamente el valor
 *   restante para que Σ de las salidas = total_cost del lote.
 * - Faltante (BR-17): con `inventory.allow_sale_without_stock` = 'false' (por defecto) se rechaza y no
 *   se escribe nada; con 'true' el excedente sale del último lote de la fila y deja su saldo negativo
 *   (alerta de ajuste pendiente), costeado kg × costo unitario de ese lote. Sin ningún lote usable se
 *   rechaza siempre: no se puede inventar un costo.
 * - No escribe `audit_logs`: la auditoría (C15) es de los ajustes de inventario, no de las ventas.
 */
class ConsumeStockFefoAction
{
    public const ALLOW_SALE_WITHOUT_STOCK = 'inventory.allow_sale_without_stock';

    public function __construct(
        private readonly StockBalance $stock = new StockBalance,
        private readonly StockExitWriter $exits = new StockExitWriter,
    ) {}

    public function execute(
        int $productId,
        string $kg,
        MovementType $type,
        string $referenceType,
        int $referenceId,
        int $userId,
        ?string $reason = null,
    ): FefoConsumption {
        $requested = DecimalScale::exact($kg, 3);

        if ($requested->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('La cantidad a sacar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($productId, $requested, $type, $referenceType, $referenceId, $userId, $reason) {
            $queue = $this->lockUsableBatches($productId);

            if ($queue->isEmpty()) {
                throw new RuntimeException('El producto no tiene lotes con stock disponible y vigente; no se puede registrar la salida.');
            }

            $available = new Number('0');
            foreach ($queue as $entry) {
                $available += new Number($entry['balance']);
            }

            $shortfall = $requested > $available;

            if ($shortfall && ! $this->allowsSaleWithoutStock()) {
                throw new RuntimeException(
                    "Stock insuficiente: se piden {$requested} kg y solo hay {$available} kg disponibles y vigentes."
                );
            }

            $allocations = [];
            $remaining = $requested;
            $lastIndex = $queue->count() - 1;

            foreach ($queue->values() as $index => $entry) {
                if ($remaining->compare(new Number('0')) <= 0) {
                    break;
                }

                /** @var Batch $batch */
                $batch = $entry['batch'];
                $balance = new Number($entry['balance']);
                $take = $remaining < $balance ? $remaining : $balance;

                // La parte que deja el lote en 0,000 kg toma exactamente el valor restante (C9).
                $cost = $this->exits->costOf($batch, $take, $balance);

                $allocations[] = $this->write($batch, $take, $cost, $type, $referenceType, $referenceId, $userId, $reason);
                $remaining -= $take;

                // Faltante permitido: el excedente sale del último lote y lo deja en negativo.
                if ($index === $lastIndex && $remaining->compare(new Number('0')) > 0) {
                    $excessCost = UnitAmount::fromString((string) $batch->unit_cost)->times((string) $remaining);
                    $allocations[] = $this->write($batch, $remaining, $excessCost, $type, $referenceType, $referenceId, $userId, $reason);
                    $remaining = new Number('0');
                }
            }

            $total = Money::zero();
            foreach ($allocations as $allocation) {
                $total = $total->add($allocation->cost);
            }

            return new FefoConsumption($allocations, $total);
        });
    }

    /**
     * Bloquea los lotes del producto (orden de id, sin interbloqueos), relee sus saldos y devuelve la fila
     * FEFO: solo saldo > 0 y no vencidos.
     *
     * @return Collection<int, array{batch: Batch, balance: string, expires_on: ?string}>
     */
    private function lockUsableBatches(int $productId): Collection
    {
        $batches = Batch::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $today = CarbonImmutable::now('UTC')->toDateString();

        return $batches
            ->map(fn (Batch $batch) => [
                'batch' => $batch,
                'balance' => $this->stock->forBatchLocked($batch->id),
                'expires_on' => $this->expiryDate($batch),
            ])
            ->filter(fn (array $entry) => (new Number($entry['balance']))->compare(new Number('0')) > 0)
            // Vencido = la fecha (UTC) es anterior a hoy; vencer hoy todavía es vendible.
            ->filter(fn (array $entry) => $entry['expires_on'] === null || $entry['expires_on'] >= $today)
            ->sort(function (array $a, array $b): int {
                // Sin fecha al final (DEC-02 A); luego recepción y luego id.
                if (($a['expires_on'] === null) !== ($b['expires_on'] === null)) {
                    return $a['expires_on'] === null ? 1 : -1;
                }

                return [$a['expires_on'], $a['batch']->received_at, $a['batch']->id]
                    <=> [$b['expires_on'], $b['batch']->received_at, $b['batch']->id];
            })
            ->values();
    }

    /**
     * Fecha (UTC, sin convertir a Caracas) de vencimiento del lote como 'Y-m-d', o null si no tiene.
     */
    private function expiryDate(Batch $batch): ?string
    {
        $raw = $batch->getRawOriginal('expires_at');

        return $raw === null ? null : CarbonImmutable::parse((string) $raw, 'UTC')->toDateString();
    }

    private function write(
        Batch $batch,
        Number $kg,
        Money $cost,
        MovementType $type,
        string $referenceType,
        int $referenceId,
        int $userId,
        ?string $reason,
    ): FefoAllocation {
        $this->exits->write($batch, $kg, $cost, $type, $referenceType, $referenceId, $userId, $reason);

        return new FefoAllocation($batch, (string) DecimalScale::round($kg, 3), $cost);
    }

    /**
     * BR-17 [abierto]: se guarda como texto 'false'/'true'; por defecto se bloquea.
     */
    private function allowsSaleWithoutStock(): bool
    {
        return strtolower(trim(Setting::valueFor(self::ALLOW_SALE_WITHOUT_STOCK, 'false'))) === 'true';
    }
}
