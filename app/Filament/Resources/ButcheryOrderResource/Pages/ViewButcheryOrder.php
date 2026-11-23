<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryOrderResource\Pages;

use App\Domain\Butchery\Actions\VoidButcheryOrderAction;
use App\Domain\Butchery\Models\ButcheryOrder;
use App\Filament\Resources\ButcheryOrderResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewButcheryOrder extends ViewRecord
{
    protected static string $resource = ButcheryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // BR-03/C12: anular una orden abierta o cerrada requiere un permiso propio y un motivo.
            Action::make('void')
                ->label('Anular')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->order()->status, ['open', 'closed'], true)
                    && (auth()->user()?->can('void', $this->order()) ?? false))
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required()->rule('regex:/\S/')->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $order = $this->order();

                    abort_unless(auth()->user()?->can('void', $order), 403);

                    try {
                        app(VoidButcheryOrderAction::class)->execute($order->id, trim((string) $data['reason']), (int) auth()->id());
                    } catch (RuntimeException $e) {
                        Notification::make()->title('No se pudo anular la orden')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->refreshFormData(['status', 'void_reason']);
                    Notification::make()->title('Orden anulada')->success()->send();
                }),
        ];
    }

    private function order(): ButcheryOrder
    {
        /** @var ButcheryOrder $record */
        $record = $this->getRecord();

        return $record;
    }
}
