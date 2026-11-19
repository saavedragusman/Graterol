<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryOrderLine;
use App\Domain\Settings\Models\Setting;
use App\Filament\Resources\ButcheryOrderResource\Pages\ListButcheryOrders;
use App\Filament\Resources\ButcheryOrderResource\Pages\ViewButcheryOrder;
use App\Support\ValueObjects\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * BR-09 a BR-12: consulta de órdenes de despiece. La operación (abrir, pesar,
 * cerrar) vive en la PWA; aquí solo se consulta y, con permiso propio, se
 * anula (BR-03) — mismo reparto que Purchasing en M2 (Filament = oficina,
 * PWA = piso). Sin pantalla de referencia dibujada para este recurso.
 * Autorización: ButcheryOrderPolicy (butchery.process / butchery.void).
 */
class ButcheryOrderResource extends Resource
{
    protected static ?string $model = ButcheryOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scissors';

    protected static string|UnitEnum|null $navigationGroup = 'Despiece';

    protected static ?string $modelLabel = 'orden de despiece';

    protected static ?string $pluralModelLabel = 'órdenes de despiece';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'open' => 'Abierta',
        'closed' => 'Cerrada',
        'voided' => 'Anulada',
    ];

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Orden')->columns(3)->schema([
                TextEntry::make('code')->label('Código'),
                TextEntry::make('batch.code')->label('Lote de entrada'),
                TextEntry::make('butcher.name')->label('Carnicero'),
                TextEntry::make('input_weight_kg')->label('Kg de entrada'),
                TextEntry::make('expected_shrinkage_percent')->label('Merma esperada (%)'),
                TextEntry::make('actual_shrinkage_kg')->label('Merma real (kg)')->placeholder('Pendiente'),
                TextEntry::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
                TextEntry::make('void_reason')->label('Motivo de anulación')->placeholder('—')
                    ->visible(fn (ButcheryOrder $record): bool => $record->status === 'voided'),
            ]),
            Section::make('Cortes · real frente a plantilla')->schema([
                // BR-11: la desviación en puntos frente al % esperado de la plantilla.
                RepeatableEntry::make('lines')->label('Cortes')->columns(5)->schema([
                    TextEntry::make('cutProduct.name')->label('Corte'),
                    TextEntry::make('expected_yield_percent')->label('% esperado'),
                    TextEntry::make('expected_weight_kg')->label('Kg esperados'),
                    TextEntry::make('actual_weight_kg')->label('Kg reales')->placeholder('Pendiente'),
                    TextEntry::make('deviation_percent')->label('Desviación (pts)')
                        ->placeholder('—')
                        ->color(fn (?string $state): string => $state !== null && bccomp(ltrim($state, '-'), self::deviationThreshold(), 2) > 0 ? 'warning' : 'success'),
                    TextEntry::make('allocated_cost')->label('Costo asignado')->placeholder('—')
                        ->formatStateUsing(fn (?string $state, ButcheryOrderLine $record): string => Money::display($state, (string) $record->order->batch->currency)),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['batch', 'butcher']))
            ->columns([
                TextColumn::make('code')->label('Código')->searchable(),
                TextColumn::make('batch.code')->label('Lote')->searchable(),
                TextColumn::make('butcher.name')->label('Carnicero'),
                TextColumn::make('input_weight_kg')->label('Kg de entrada'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(self::STATUS_LABELS),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListButcheryOrders::route('/'),
            'view' => ViewButcheryOrder::route('/{record}'),
        ];
    }

    /**
     * DEC-05/DEC-06: umbral global configurable, nunca fijo en el código.
     */
    private static function deviationThreshold(): string
    {
        return Setting::valueFor('butchery.deviation_threshold_percent', '2.00');
    }
}
