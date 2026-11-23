<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Inventory\StockExitWriter;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use App\Support\ValueObjects\DecimalScale;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * BR-15/C15: merma tipificada sobre un solo lote, siempre con motivo y responsable.
 *
 * - Solo los cuatro tipos de merma (DEC-04); `count_adjustment` lo escribe el conteo (BR-16).
 * - El responsable (DEC-05 1 B) es un usuario existente y activo, puede ser distinto de quien registra:
 *   `user_id` = quien registra, `responsible_user_id` = quien pesó o responde.
 * - Una sola transacción (C11): bloquea el lote y relee el saldo. Una merma nunca deja el saldo por
 *   debajo de cero (solo una venta en modo permitir puede hacerlo): se rechaza y no se escribe nada.
 * - Sirve para lotes vencidos: así se da de baja el stock vencido (DEC-03 a B); no aplica el filtro de FEFO.
 * - Costo (C9): kg × costo del lote al centavo, salvo la merma que deja el lote en 0,000 kg, que toma
 *   exactamente el valor restante (ver `StockExitWriter`).
 * - Deja una fila de `audit_logs` con saldo y valor antes y después. La autorización es de la Policy
 *   de la pantalla, no de esta Action.
 */
class RegisterShrinkageAction
{
    /** @var list<MovementType> */
    public const TYPES = [
        MovementType::ShrinkageDehydration,
        MovementType::ShrinkageSlicing,
        MovementType::ShrinkageExpiry,
        MovementType::ShrinkageConfiscation,
    ];

    public function __construct(
        private readonly StockBalance $stock = new StockBalance,
        private readonly StockExitWriter $exits = new StockExitWriter,
    ) {}

    public function execute(
        int $batchId,
        MovementType $type,
        string $kg,
        ?string $reason,
        ?int $responsibleUserId,
        int $userId,
    ): InventoryMovement {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("«{$type->label()}» no es un tipo de merma; el ajuste por conteo lo genera el conteo físico.");
        }

        $requested = DecimalScale::exact($kg, 3);

        if ($requested->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException('El peso de la merma debe ser mayor a cero.');
        }

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de la merma es obligatorio.');
        }

        return DB::transaction(function () use ($batchId, $type, $requested, $reason, $responsibleUserId, $userId) {
            if ($responsibleUserId === null || ! User::query()->whereKey($responsibleUserId)->where('is_active', true)->exists()) {
                throw new InvalidArgumentException('El responsable debe ser un usuario existente y activo.');
            }

            $batch = Batch::query()->whereKey($batchId)->lockForUpdate()->firstOrFail();

            $balance = new Number($this->stock->forBatchLocked($batch->id));

            if ($balance->compare(new Number('0')) <= 0) {
                throw new RuntimeException('El lote no tiene saldo para dar de baja.');
            }

            if ($requested > $balance) {
                throw new RuntimeException("La merma ({$requested} kg) supera el saldo del lote ({$balance} kg).");
            }

            $valueBefore = $this->stock->valueForBatchLocked($batch->id);
            $cost = $this->exits->costOf($batch, $requested, $balance);

            $movement = $this->exits->write(
                $batch, $requested, $cost, $type,
                referenceType: null, referenceId: null,
                userId: $userId, reason: $reason, responsibleUserId: $responsibleUserId,
            );

            AuditLog::create([
                'auditable_type' => 'batch',
                'auditable_id' => $batch->id,
                'action' => 'inventory.shrinkage_registered',
                'user_id' => $userId,
                'reason' => $reason,
                'before' => [
                    'balance_kg' => (string) $balance,
                    'value' => $valueBefore->toInternalString(),
                    'type' => $type->value,
                    'responsible_user_id' => $responsibleUserId,
                ],
                'after' => [
                    'balance_kg' => $this->stock->forBatchLocked($batch->id),
                    'value' => $this->stock->valueForBatchLocked($batch->id)->toInternalString(),
                    'type' => $type->value,
                    'responsible_user_id' => $responsibleUserId,
                ],
            ]);

            return $movement;
        });
    }
}
