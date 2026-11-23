<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Queries\StockBalance;
use App\Support\ValueObjects\DecimalScale;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;
use BcMath\Number;

/**
 * BR-14/BR-15/C9: costeo y escritura de una salida de un lote, compartidos por FEFO y mermas.
 *
 * Ambos métodos deben llamarse dentro de una transacción con el lote bloqueado (C11).
 */
class StockExitWriter
{
    public function __construct(private readonly StockBalance $stock = new StockBalance) {}

    /**
     * Costo (positivo) de sacar `$kg` de un lote cuyo saldo actual es `$balance`: kg × costo unitario
     * redondeado al centavo, salvo la salida que deja el lote en 0,000 kg, que toma exactamente el
     * valor restante para que Σ de las salidas = total_cost del lote (C9).
     */
    public function costOf(Batch $batch, Number $kg, Number $balance): Money
    {
        return $kg->compare($balance) === 0
            ? $this->stock->valueForBatchLocked($batch->id)
            : UnitAmount::fromString((string) $batch->unit_cost)->times((string) $kg);
    }

    /**
     * Escribe el movimiento negativo de la salida con su monto exacto (negativo).
     */
    public function write(
        Batch $batch,
        Number $kg,
        Money $cost,
        MovementType $type,
        ?string $referenceType,
        ?int $referenceId,
        int $userId,
        ?string $reason,
        ?int $responsibleUserId = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'batch_id' => $batch->id,
            'type' => $type->value,
            'quantity' => '-'.DecimalScale::round($kg, 3),
            'unit_cost' => $batch->unit_cost,
            'total_cost' => Money::zero()->sub($cost)->toInternalString(),
            'user_id' => $userId,
            'responsible_user_id' => $responsibleUserId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
