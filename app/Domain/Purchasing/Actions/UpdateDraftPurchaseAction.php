<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Actions\Concerns\ValidatesPurchasableProducts;
use App\Domain\Purchasing\Models\Purchase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-07/C12: corrige la compra mientras esté en `draft` (DEC-01 sub-decisión).
 * Una vez `received`, deja de editarse; la corrección va por un documento
 * inverso, no por esta Action. Solo campos de encabezado no sensibles son
 * editables: ni el estado, la tasa BCV ni el proveedor se cambian aquí.
 */
class UpdateDraftPurchaseAction
{
    use ValidatesPurchasableProducts;

    private const EDITABLE_HEADER_FIELDS = [
        'supplier_invoice_number',
        'supplier_invoice_date',
        'currency',
        'freight_amount',
        'other_costs_amount',
        'other_costs_note',
    ];

    /**
     * @param  array<string, mixed>  $headerAttributes
     * @param  array<int, array{product_id: int, invoiced_weight_kg: string, line_amount: string, invoiced_pieces?: int|null}>|null  $lines
     */
    public function execute(Purchase $purchase, array $headerAttributes = [], ?array $lines = null): Purchase
    {
        if ($lines !== null) {
            $this->assertPurchasableProducts($lines);
        }

        return DB::transaction(function () use ($purchase, $headerAttributes, $lines) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'draft') {
                throw new RuntimeException('Solo se puede editar una compra en borrador (C12).');
            }

            $safeAttributes = Arr::only($headerAttributes, self::EDITABLE_HEADER_FIELDS);

            if ($safeAttributes !== []) {
                $locked->update($safeAttributes);
            }

            if ($lines !== null) {
                $locked->lines()->delete();

                foreach ($lines as $line) {
                    $locked->lines()->create([
                        'product_id' => $line['product_id'],
                        'invoiced_weight_kg' => $line['invoiced_weight_kg'],
                        'invoiced_pieces' => $line['invoiced_pieces'] ?? null,
                        'line_amount' => $line['line_amount'],
                    ]);
                }
            }

            return $locked->fresh('lines');
        });
    }
}
