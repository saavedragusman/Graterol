<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExchangeRateResource\Pages;

use App\Domain\Settings\Actions\RegisterManualExchangeRateAction;
use App\Domain\Settings\Models\ExchangeRate;
use App\Filament\Resources\ExchangeRateResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;
use RuntimeException;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('register')
                ->label('Registrar tasa')
                ->icon('heroicon-o-plus')
                ->authorize('create', ExchangeRate::class)
                ->modalHeading('Registrar tasa BCV')
                ->modalSubmitActionLabel('Registrar')
                ->schema([
                    // Texto, no numeric: el valor llega como cadena (sin float, C9) y admite coma o punto.
                    TextInput::make('rate')->label('Tasa (Bs por USD)')
                        ->required()
                        ->inputMode('decimal')
                        ->helperText('Hasta 4 decimales; mayor que cero.'),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(RegisterManualExchangeRateAction::class)->execute((string) ($data['rate'] ?? ''), $user);
                    } catch (InvalidArgumentException|RuntimeException $e) {
                        Notification::make()->title('No se pudo registrar la tasa')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Tasa registrada')->success()->send();
                }),
        ];
    }
}
