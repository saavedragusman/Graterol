<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Filament\Resources\PurchaseResource;
use App\Filament\Resources\PurchaseResource\Pages\Concerns\MapsPurchaseLines;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreatePurchase extends CreateRecord
{
    use MapsPurchaseLines;

    protected static string $resource = PurchaseResource::class;

    /**
     * C3: la regla vive en CreatePurchaseAction; aquí solo se orquesta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreatePurchaseAction::class)->execute(
                supplierId: (int) $data['supplier_id'],
                invoiceNumber: (string) $data['supplier_invoice_number'],
                invoiceDate: CarbonImmutable::parse((string) $data['supplier_invoice_date']),
                lines: $this->mapLines($data['lines'] ?? []),
                freightAmount: $this->money($data['freight_amount'] ?? null),
                otherCostsAmount: $this->money($data['other_costs_amount'] ?? null),
                otherCostsNote: filled($data['other_costs_note'] ?? null) ? (string) $data['other_costs_note'] : null,
                currency: (string) ($data['currency'] ?? 'USD'),
                createdBy: auth()->id(),
            );
        } catch (RuntimeException $e) {
            Notification::make()->title('No se pudo crear la compra')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
