<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryTemplateResource\Pages\Concerns;

/**
 * Convierte el estado del formulario de Filament al formato que espera
 * CreateButcheryTemplateAction/UpdateButcheryTemplateAction (% como string,
 * nunca float, C9).
 */
trait MapsButcheryTemplateLines
{
    /**
     * @param  array<array-key, array<string, mixed>>  $formLines
     * @return array<int, array{cut_product_id: int, expected_yield_percent: string}>
     */
    protected function mapLines(array $formLines): array
    {
        $lines = [];

        foreach ($formLines as $line) {
            $lines[] = [
                'cut_product_id' => (int) $line['cut_product_id'],
                'expected_yield_percent' => (string) $line['expected_yield_percent'],
            ];
        }

        return $lines;
    }
}
