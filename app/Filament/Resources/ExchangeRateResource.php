<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Settings\Models\ExchangeRate;
use App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Support\Formatting\EsVeNumberFormatter;
use App\Support\ValueObjects\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * S4/S6: «Configuración › Tasa BCV». Historial de solo lectura y registro manual
 * (acción de cabecera). La regla vive en RegisterManualExchangeRateAction;
 * la autorización, en ExchangeRatePolicy (rates.manage).
 */
class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'tasa';

    protected static ?string $pluralModelLabel = 'tasas';

    protected static ?string $navigationLabel = 'Tasa BCV';

    public const SOURCE_LABELS = [
        'automatic' => 'Automática',
        'manual' => 'Manual',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('effective_at')->label('Fecha y hora')->dateTime()->sortable(),
                TextColumn::make('rate')->label('Tasa (Bs por USD)')
                    ->alignEnd()
                    ->extraAttributes(['class' => 'tabular-nums'])
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? '—'
                        : EsVeNumberFormatter::format($state, Money::INTERNAL_SCALE)), // la tasa se ve con 4 decimales
                TextColumn::make('source')->label('Fuente')->badge()
                    ->formatStateUsing(fn (?string $state): string => self::SOURCE_LABELS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === 'automatic' ? 'info' : 'gray'),
                TextColumn::make('registeredBy.name')->label('Registrada por')->default('Sistema'),
            ])
            ->filters([
                SelectFilter::make('source')->label('Fuente')->options(self::SOURCE_LABELS),
            ])
            ->defaultSort('effective_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
        ];
    }
}
