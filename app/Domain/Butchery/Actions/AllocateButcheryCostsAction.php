<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Support\ValueObjects\DecimalScale;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\UnitAmount;
use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-12: reparte el costo total del lote de entrada entre los cortes por
 * valor relativo de mercado, no por peso: Costo_i = C_total × (kg_i·p_i) /
 * Σ(kg_j·p_j). La merma (y cualquier corte con peso real cero) no lleva
 * costo. Reparto en dos pasos (decisión humana, caso dorado BR-13): primero
 * cada parte se redondea half-up a 4 decimales y el residuo va al corte de
 * mayor valor (kg_i·p_i más alto, el primero en empate); luego cada costo se
 * redondea half-up al centavo y el residuo de centavos va al mismo corte, de
 * modo que Σ costos = C_total exacto (BR-01, BR-12).
 *
 * Este es el ÚNICO doble redondeo intencional del sistema: BR-13 (24,57 ·
 * 19,16 · …) solo se reproduce redondeando primero a 4 y después a 2
 * decimales. Todo lo demás redondea una sola vez.
 */
class AllocateButcheryCostsAction
{
    /**
     * @param  string  $totalCost  monto con 2 decimales (se rechaza uno con más decimales significativos)
     * @param  array<int|string, array{weight_kg: string, price_per_kg: string}>  $cutsByKey
     * @return array<int|string, string> costo asignado por corte (2 decimales), mismas claves, '0.00' para peso cero
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

        $totalMoney = Money::fromString($totalCost);
        $total = $totalMoney->toNumber();
        $largestValueKey = array_key_first($values);
        $largestValue = new Number('-1');

        foreach ($values as $key => $value) {
            if ($value->compare($largestValue) > 0) {
                $largestValue = $value;
                $largestValueKey = $key;
            }
        }

        // Paso 1: partes a 4 decimales (half-up) y residuo de 4 decimales al corte de mayor valor.
        $shares = [];
        $sharesSum = new Number('0');

        foreach ($values as $key => $value) {
            $shares[$key] = DecimalScale::round($value->mul($total)->div($totalValue, 12), UnitAmount::SCALE);
            $sharesSum = $sharesSum->add($shares[$key]);
        }

        $shares[$largestValueKey] = $shares[$largestValueKey]->add($total->sub($sharesSum));

        // Paso 2: costo final a 2 decimales (half-up) y residuo de centavos al mismo corte.
        $allocated = [];
        $assignedSum = Money::zero();

        foreach ($shares as $key => $share) {
            $allocated[$key] = Money::rounded($share);
            $assignedSum = $assignedSum->add($allocated[$key]);
        }

        $residue = $totalMoney->sub($assignedSum);

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
