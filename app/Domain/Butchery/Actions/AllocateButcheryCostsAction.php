<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Support\ValueObjects\Money;
use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-12: reparte el costo total del lote de entrada entre los cortes por
 * valor relativo de mercado, no por peso: Costo_i = C_total × (kg_i·p_i) /
 * Σ(kg_j·p_j). La merma (y cualquier corte con peso real cero) no lleva
 * costo. El residuo de redondeo va al corte de mayor valor (kg_i·p_i más
 * alto), mismo criterio que `AllocateCostsAction` usa en M2 con "mayor monto".
 */
class AllocateButcheryCostsAction
{
    /**
     * @param  array<int|string, array{weight_kg: string, price_per_kg: string}>  $cutsByKey
     * @return array<int|string, string> costo asignado por corte, mismas claves, '0.0000' para peso cero
     */
    public function execute(string $totalCost, array $cutsByKey): array
    {
        $values = [];
        $totalValue = new Number('0');

        foreach ($cutsByKey as $key => $cut) {
            $weight = new Number($cut['weight_kg']);

            if ($weight->compare(new Number('0')) <= 0) {
                continue;
            }

            $value = $weight->mul(new Number($cut['price_per_kg']));
            $values[$key] = $value;
            $totalValue = $totalValue->add($value);
        }

        if ($values === [] || $totalValue->compare(new Number('0')) === 0) {
            throw new InvalidArgumentException('No se puede costear sin al menos un corte con peso real y valor de mercado.');
        }

        $total = new Number($totalCost);
        $allocated = [];
        $assignedSum = Money::zero();
        $largestValueKey = array_key_first($values);
        $largestValue = new Number('-1');

        foreach ($values as $key => $value) {
            $share = Money::fromString((string) $value->mul($total)->div($totalValue, 10));
            $allocated[$key] = $share;
            $assignedSum = $assignedSum->add($share);

            if ($value->compare($largestValue) > 0) {
                $largestValue = $value;
                $largestValueKey = $key;
            }
        }

        $residue = Money::fromString($totalCost)->sub($assignedSum);

        if (! $residue->equals(Money::zero())) {
            $allocated[$largestValueKey] = $allocated[$largestValueKey]->add($residue);
        }

        foreach ($cutsByKey as $key => $cut) {
            if (! array_key_exists($key, $allocated)) {
                $allocated[$key] = Money::zero();
            }
        }

        return array_map(static fn (Money $amount): string => $amount->toInternalString(), $allocated);
    }
}
