<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Support\ValueObjects\Money;
use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-07/DEC-02: reparte un monto (flete u otros costos) entre varias líneas
 * proporcional a sus kg recibidos. Cada parte se redondea una vez al centavo
 * (half-up) y el residuo va a la línea de mayor peso recibido, de modo que
 * las partes suman exactamente el total (BR-01), igual que el costeo del
 * despiece (BR-12).
 */
class AllocateCostsAction
{
    /**
     * @param  array<int|string, string>  $weightsByLine  kg recibidos por línea, indexados por la clave del llamador
     * @return array<int|string, string> monto asignado por línea (2 decimales), con las mismas claves
     */
    public function execute(string $totalAmount, array $weightsByLine): array
    {
        if ($weightsByLine === []) {
            return [];
        }

        $totalWeight = new Number('0');

        foreach ($weightsByLine as $weight) {
            $totalWeight = $totalWeight->add(new Number($weight));
        }

        if ($totalWeight->compare(new Number('0')) === 0) {
            throw new InvalidArgumentException('No se puede prorratear sobre un peso total recibido de cero.');
        }

        $totalMoney = Money::fromString($totalAmount);
        $total = $totalMoney->toNumber();
        $allocated = [];
        $assignedSum = Money::zero();
        $largestWeightKey = array_key_first($weightsByLine);
        $largestWeight = new Number('-1');

        foreach ($weightsByLine as $key => $weight) {
            $weightNumber = new Number($weight);
            $share = Money::rounded($weightNumber->mul($total)->div($totalWeight, 12));
            $allocated[$key] = $share;
            $assignedSum = $assignedSum->add($share);

            if ($weightNumber->compare($largestWeight) > 0) {
                $largestWeight = $weightNumber;
                $largestWeightKey = $key;
            }
        }

        $residue = $totalMoney->sub($assignedSum);

        if (! $residue->equals(Money::zero())) {
            $allocated[$largestWeightKey] = $allocated[$largestWeightKey]->add($residue);
        }

        return array_map(static fn (Money $amount): string => $amount->toInternalString(), $allocated);
    }
}
