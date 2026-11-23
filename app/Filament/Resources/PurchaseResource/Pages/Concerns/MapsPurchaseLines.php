<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseResource\Pages\Concerns;

/**
 * Convierte el estado del formulario de Filament al formato que esperan
 * las Actions de Purchasing (montos y pesos como string, nunca float, C9).
 */
trait MapsPurchaseLines
{
    /**
     * @param  array<array-key, array<string, mixed>>  $formLines
     * @return array<int, array{product_id: int, invoiced_weight_kg: string, line_amount: string, invoiced_pieces: int|null}>
     */
    protected function mapLines(array $formLines): array
    {
        $lines = [];

        foreach ($formLines as $line) {
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'invoiced_weight_kg' => (string) $line['invoiced_weight_kg'],
                'line_amount' => (string) $line['line_amount'],
                'invoiced_pieces' => filled($line['invoiced_pieces'] ?? null) ? (int) $line['invoiced_pieces'] : null,
            ];
        }

        return $lines;
    }

    protected function money(mixed $value): string
    {
        return filled($value) ? (string) $value : '0.00';
    }
}
