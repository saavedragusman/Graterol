<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Actions\Concerns\ValidatesPurchasableProducts;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-02/BR-07/DEC-01: crea la compra en borrador (oficina), con la tasa BCV
 * vigente guardada (C13). Solo se puede comprar materia prima o reventa
 * (DEC-05); los derivados salen únicamente del despiece propio (M3).
 */
class CreatePurchaseAction
{
    use ValidatesPurchasableProducts;

    /**
     * @param  array<int, array{product_id: int, invoiced_weight_kg: string, line_amount: string, invoiced_pieces?: int|null}>  $lines
     */
    public function execute(
        int $supplierId,
        string $invoiceNumber,
        CarbonInterface $invoiceDate,
        array $lines,
        string $freightAmount = '0.00',
        string $otherCostsAmount = '0.00',
        ?string $otherCostsNote = null,
        ?string $currency = null,
        ?int $createdBy = null,
    ): Purchase {
        // S10: sin moneda explícita, la que se configuró en Settings.
        $currency ??= app(CompanySettings::class)->defaultCurrency();

        $rate = ExchangeRate::current();

        if ($rate === null) {
            throw new RuntimeException('No hay una tasa BCV registrada; registre una antes de crear la compra.');
        }

        $this->assertPurchasableProducts($lines);

        return DB::transaction(function () use ($supplierId, $invoiceNumber, $invoiceDate, $lines, $freightAmount, $otherCostsAmount, $otherCostsNote, $currency, $createdBy, $rate) {
            $purchase = Purchase::create([
                'supplier_id' => $supplierId,
                'supplier_invoice_number' => $invoiceNumber,
                'supplier_invoice_date' => $invoiceDate,
                'currency' => $currency,
                'exchange_rate' => $rate->rate,
                'freight_amount' => $freightAmount,
                'other_costs_amount' => $otherCostsAmount,
                'other_costs_note' => $otherCostsNote,
                'status' => 'draft',
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $purchase->lines()->create([
                    'product_id' => $line['product_id'],
                    'invoiced_weight_kg' => $line['invoiced_weight_kg'],
                    'invoiced_pieces' => $line['invoiced_pieces'] ?? null,
                    'line_amount' => $line['line_amount'],
                ]);
            }

            return $purchase->load('lines');
        });
    }
}
