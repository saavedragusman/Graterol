<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Actions\Concerns\ValidatesTemplatePercentages;
use App\Domain\Butchery\Models\ButcheryTemplate;
use Illuminate\Support\Facades\DB;

/**
 * BR-08/DEC-03: edita la plantilla en el sitio (calibración). No afecta las
 * órdenes ya abiertas: cada una congeló su propio % al abrirse.
 */
class UpdateButcheryTemplateAction
{
    use ValidatesTemplatePercentages;

    /**
     * @param  array<int, array{cut_product_id: int, expected_yield_percent: string}>  $lines
     */
    public function execute(
        int $templateId,
        string $name,
        string $expectedShrinkagePercent,
        array $lines,
    ): ButcheryTemplate {
        $this->assertLinesSumTo100($expectedShrinkagePercent, $lines);

        return DB::transaction(function () use ($templateId, $name, $expectedShrinkagePercent, $lines) {
            $template = ButcheryTemplate::findOrFail($templateId);

            $template->update([
                'name' => $name,
                'expected_shrinkage_percent' => $expectedShrinkagePercent,
            ]);

            $template->lines()->delete();

            foreach ($lines as $line) {
                $template->lines()->create([
                    'cut_product_id' => $line['cut_product_id'],
                    'expected_yield_percent' => $line['expected_yield_percent'],
                ]);
            }

            return $template->load('lines');
        });
    }
}
