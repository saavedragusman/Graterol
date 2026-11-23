<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Domain\Purchasing\Actions\UpdateDraftPurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Filament\Resources\PurchaseResource;
use App\Filament\Resources\PurchaseResource\Pages\Concerns\MapsPurchaseLines;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EditPurchase extends EditRecord
{
    use MapsPurchaseLines;

    protected static string $resource = PurchaseResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Purchase $purchase */
        $purchase = $this->getRecord();

        $data['lines'] = $purchase->lines->map(fn ($line): array => [
            'product_id' => $line->product_id,
            'invoiced_weight_kg' => $line->invoiced_weight_kg,
            'invoiced_pieces' => $line->invoiced_pieces,
            'line_amount' => $line->line_amount,
        ])->all();

        return $data;
    }

    /**
     * C3/C12: la regla (solo `draft`) vive en UpdateDraftPurchaseAction.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Purchase $record */
        try {
            return app(UpdateDraftPurchaseAction::class)->execute(
                $record,
                [
                    'supplier_invoice_number' => $data['supplier_invoice_number'],
                    'supplier_invoice_date' => $data['supplier_invoice_date'],
                    'currency' => $data['currency'],
                    'freight_amount' => $this->money($data['freight_amount'] ?? null),
                    'other_costs_amount' => $this->money($data['other_costs_amount'] ?? null),
                    'other_costs_note' => filled($data['other_costs_note'] ?? null) ? $data['other_costs_note'] : null,
                ],
                $this->mapLines($data['lines'] ?? []),
            );
        } catch (RuntimeException $e) {
            Notification::make()->title('No se pudo guardar la compra')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
