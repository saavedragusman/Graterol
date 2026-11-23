<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Actions;

use App\Domain\Butchery\Actions\Concerns\ValidatesTemplatePercentages;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BR-08: cada materia prima tiene una plantilla: cortes con % estándar más
 * merma esperada, que suman 100 %. DEC-02: solo una plantilla activa por
 * materia prima; crear una nueva desactiva la anterior.
 */
class CreateButcheryTemplateAction
{
    use ValidatesTemplatePercentages;

    /**
     * @param  array<int, array{cut_product_id: int, expected_yield_percent: string}>  $lines
     */
    public function execute(
        int $rawMaterialProductId,
        string $name,
        string $expectedShrinkagePercent,
        array $lines,
        int $createdByUserId,
    ): ButcheryTemplate {
        $isRawMaterial = Product::whereKey($rawMaterialProductId)
            ->where('product_type', ProductType::RawMaterial->value)
            ->exists();

        if (! $isRawMaterial) {
            throw new InvalidArgumentException('La plantilla solo se puede crear para un producto de tipo materia prima (BR-04).');
        }

        $this->assertLinesSumTo100($expectedShrinkagePercent, $lines);

        return DB::transaction(function () use ($rawMaterialProductId, $name, $expectedShrinkagePercent, $lines, $createdByUserId) {
            ButcheryTemplate::where('raw_material_product_id', $rawMaterialProductId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template = ButcheryTemplate::create([
                'raw_material_product_id' => $rawMaterialProductId,
                'name' => $name,
                'expected_shrinkage_percent' => $expectedShrinkagePercent,
                'is_active' => true,
                'created_by' => $createdByUserId,
            ]);

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
