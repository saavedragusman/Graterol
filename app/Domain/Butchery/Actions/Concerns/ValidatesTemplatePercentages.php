<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions\Concerns;

use BcMath\Number;
use InvalidArgumentException;

/**
 * BR-08: los % de los cortes más la merma esperada deben sumar 100
 * (tolerancia ±0,01 por redondeo).
 */
trait ValidatesTemplatePercentages
{
    private const SUM_TOLERANCE = '0.01';

    /**
     * @param  array<int, array{cut_product_id: int, expected_yield_percent: string}>  $lines
     */
    private function assertLinesSumTo100(string $expectedShrinkagePercent, array $lines): void
    {
        $sum = new Number($expectedShrinkagePercent);

        foreach ($lines as $line) {
            $sum = $sum->add(new Number($line['expected_yield_percent']));
        }

        $difference = $sum->sub(new Number('100'));
        $absoluteDifference = $difference->compare(new Number('0')) < 0 ? $difference->mul(new Number('-1')) : $difference;

        if ($absoluteDifference->compare(new Number(self::SUM_TOLERANCE)) > 0) {
            throw new InvalidArgumentException("La suma de los % de los cortes más la merma esperada debe ser 100 (BR-08); salió {$sum}.");
        }
    }
}
