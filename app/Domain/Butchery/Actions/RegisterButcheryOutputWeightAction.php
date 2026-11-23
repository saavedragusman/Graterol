<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryOrderLine;
use BcMath\Number;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * BR-09: el carnicero pesa cada corte (puede ser 0). No cierra ni costea
 * todavía: eso ocurre al cerrar la orden.
 */
class RegisterButcheryOutputWeightAction
{
    public function execute(int $orderLineId, string $actualWeightKg): ButcheryOrderLine
    {
        if ((new Number($actualWeightKg))->compare(new Number('0')) < 0) {
            throw new InvalidArgumentException('El peso real no puede ser negativo.');
        }

        return DB::transaction(function () use ($orderLineId, $actualWeightKg) {
            $line = ButcheryOrderLine::findOrFail($orderLineId);
            $order = ButcheryOrder::whereKey($line->order_id)->lockForUpdate()->first();

            if ($order === null || $order->status !== 'open') {
                throw new RuntimeException('Solo se puede registrar peso en una orden abierta (C12).');
            }

            $line->update(['actual_weight_kg' => $actualWeightKg]);

            return $line;
        });
    }
}
