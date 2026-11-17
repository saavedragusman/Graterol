<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Domain\Purchasing\Actions\VoidPurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Filament\Resources\PurchaseResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // BR-03/C12: solo una compra recibida se anula, con permiso propio y motivo.
            Action::make('void')
                ->label('Anular')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->purchase()->status === 'received'
                    && (auth()->user()?->can('void', $this->purchase()) ?? false))
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required()->rule('regex:/\S/')->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $purchase = $this->purchase();

                    abort_unless(auth()->user()?->can('void', $purchase), 403);

                    try {
                        app(VoidPurchaseAction::class)->execute($purchase, trim((string) $data['reason']), (int) auth()->id());
                    } catch (RuntimeException $e) {
                        Notification::make()->title('No se pudo anular la compra')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->refreshFormData(['status', 'void_reason']);
                    Notification::make()->title('Compra anulada')->success()->send();
                }),
        ];
    }

    private function purchase(): Purchase
    {
        /** @var Purchase $record */
        $record = $this->getRecord();

        return $record;
    }
}
