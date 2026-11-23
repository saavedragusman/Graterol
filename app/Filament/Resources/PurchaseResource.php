<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductType;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\PurchaseLine;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\CompanySettings;
use App\Filament\Resources\PurchaseResource\Pages\CreatePurchase;
use App\Filament\Resources\PurchaseResource\Pages\EditPurchase;
use App\Filament\Resources\PurchaseResource\Pages\ListPurchases;
use App\Filament\Resources\PurchaseResource\Pages\ViewPurchase;
use App\Support\ValueObjects\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * M2/DEC-01: la compra se crea y se corrige en Filament mientras está en
 * `draft`; el pesaje y la recepción ocurren en la PWA. Aquí solo se orquesta:
 * la regla vive en las Actions de Purchasing (C3).
 * Autorización: PurchasePolicy (purchasing.view / create / void).
 */
class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'compra';

    protected static ?string $pluralModelLabel = 'compras';

    // Filament capitaliza cada palabra del plural; la etiqueta del menú va en español normal.
    protected static ?string $navigationLabel = 'Compras';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'draft' => 'Borrador',
        'received' => 'Recibida',
        'voided' => 'Anulada',
    ];

    /**
     * Solo `draft` se edita (C12). PurchasePolicy no define `update`:
     * crear y corregir un borrador comparten el permiso purchasing.create.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Purchase
            && $record->status === 'draft'
            && static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Factura del proveedor')->columns(2)->schema([
                Select::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    // UpdateDraftPurchaseAction no cambia el proveedor.
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),
                TextInput::make('supplier_invoice_number')->label('N.º de factura')->required()->maxLength(100),
                DatePicker::make('supplier_invoice_date')->label('Fecha de la factura')->required(),
                // C13: la compra guarda su moneda y la tasa BCV vigente al crearla.
                Select::make('currency')
                    ->label('Moneda de la factura')
                    ->options(['USD' => 'Dólares ($)', 'VES' => 'Bolívares (Bs)'])
                    ->default(fn (): string => app(CompanySettings::class)->defaultCurrency())
                    ->required()
                    ->helperText('Si es en Bs, se guarda con la tasa BCV del día.'),
            ]),
            Section::make('Líneas')->schema([
                Repeater::make('lines')
                    ->label('Líneas')
                    ->minItems(1)
                    ->required()
                    ->columns(4)
                    ->schema([
                        // DEC-05: los derivados no se compran; ni se ofrecen.
                        Select::make('product_id')
                            ->label('Producto')
                            ->options(fn (): array => Product::query()
                                ->whereIn('product_type', [ProductType::RawMaterial->value, ProductType::Resale->value])
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('invoiced_weight_kg')->label('Kg facturados')->numeric()->minValue(0.001)->required(),
                        TextInput::make('invoiced_pieces')->label('Piezas facturadas')->integer()->minValue(0),
                        TextInput::make('line_amount')->label('Monto de la línea')->numeric()->minValue(0)->step('0.01')->rule('decimal:0,2')->required(),
                    ]),
            ]),
            Section::make('Costos adicionales')->columns(3)->schema([
                TextInput::make('freight_amount')->label('Flete')->numeric()->minValue(0)->step('0.01')->rule('decimal:0,2')->default('0'),
                TextInput::make('other_costs_amount')->label('Otros costos')->numeric()->minValue(0)->step('0.01')->rule('decimal:0,2')->default('0'),
                TextInput::make('other_costs_note')->label('Nota de otros costos')->maxLength(255),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Compra')->columns(3)->schema([
                TextEntry::make('supplier.name')->label('Proveedor'),
                TextEntry::make('supplier_invoice_number')->label('N.º de factura'),
                TextEntry::make('supplier_invoice_date')->label('Fecha de la factura')->date(),
                TextEntry::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
                TextEntry::make('currency')->label('Moneda'),
                TextEntry::make('exchange_rate')->label('Tasa BCV'),
                TextEntry::make('freight_amount')->label('Flete')
                    ->formatStateUsing(fn (?string $state, Purchase $record): string => self::dual($state, $record)['main'] ?? '—')
                    ->helperText(fn (?string $state, Purchase $record): ?string => self::dual($state, $record)['secondary']),
                TextEntry::make('other_costs_amount')->label('Otros costos')
                    ->formatStateUsing(fn (?string $state, Purchase $record): string => self::dual($state, $record)['main'] ?? '—')
                    ->helperText(fn (?string $state, Purchase $record): ?string => self::dual($state, $record)['secondary']),
                TextEntry::make('other_costs_note')->label('Nota de otros costos')->placeholder('—'),
                TextEntry::make('void_reason')->label('Motivo de anulación')
                    ->visible(fn (Purchase $record): bool => $record->status === 'voided'),
            ]),
            Section::make('Líneas')->schema([
                RepeatableEntry::make('lines')->label('Líneas')->columns(4)->schema([
                    TextEntry::make('product.name')->label('Producto'),
                    TextEntry::make('invoiced_weight_kg')->label('Kg facturados'),
                    // BR-06: peso recibido y su diferencia; se resalta si ≠ 0.
                    TextEntry::make('received_weight_kg')->label('Kg recibidos')->placeholder('Pendiente'),
                    TextEntry::make('weight_difference_kg')->label('Diferencia (kg)')
                        ->placeholder('—')
                        ->formatStateUsing(fn (?string $state): string => $state !== null && bccomp($state, '0', 3) !== 0
                            ? "(diferencia: {$state})"
                            : (string) $state)
                        ->color(fn (?string $state): ?string => $state !== null && bccomp($state, '0', 3) !== 0 ? 'warning' : null),
                    TextEntry::make('line_amount')->label('Monto de la línea')
                        ->formatStateUsing(fn (?string $state, PurchaseLine $record): string => self::dual($state, $record->purchase)['main'] ?? '—')
                        ->helperText(fn (?string $state, PurchaseLine $record): ?string => self::dual($state, $record->purchase)['secondary']),
                    // BR-07: asignaciones y costo del lote, calculados en el servidor.
                    TextEntry::make('allocated_freight')->label('Flete asignado')->placeholder('—')
                        ->formatStateUsing(fn (?string $state, PurchaseLine $record): string => self::dual($state, $record->purchase)['main'] ?? '—')
                        ->helperText(fn (?string $state, PurchaseLine $record): ?string => self::dual($state, $record->purchase)['secondary']),
                    TextEntry::make('allocated_other_costs')->label('Otros costos asignados')->placeholder('—')
                        ->formatStateUsing(fn (?string $state, PurchaseLine $record): string => self::dual($state, $record->purchase)['main'] ?? '—')
                        ->helperText(fn (?string $state, PurchaseLine $record): ?string => self::dual($state, $record->purchase)['secondary']),
                    TextEntry::make('batch.unit_cost')->label('Costo por kg del lote')->placeholder('—')
                        ->formatStateUsing(fn (?string $state, PurchaseLine $record): string => self::batchDual($state, $record)['main'] ?? '—')
                        ->helperText(fn (?string $state, PurchaseLine $record): ?string => self::batchDual($state, $record)['secondary']),
                ]),
            ]),
        ]);
    }

    /**
     * Doble monto con la tasa CONGELADA de la compra (C13, BR-02): $ arriba, Bs debajo.
     *
     * @return array{main: ?string, secondary: ?string}
     */
    private static function dual(?string $amount, Purchase $purchase): array
    {
        return Money::dualLines($amount, $purchase->currency, (string) $purchase->exchange_rate);
    }

    /**
     * Costo por kg del lote: usa la tasa congelada en el propio lote.
     *
     * @return array{main: ?string, secondary: ?string}
     */
    private static function batchDual(?string $amount, PurchaseLine $line): array
    {
        $batch = $line->batch;

        return Money::dualLines($amount, (string) $batch?->currency, $batch?->exchange_rate === null ? null : (string) $batch->exchange_rate);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier')->withCount('lines'))
            ->columns([
                TextColumn::make('supplier.name')->label('Proveedor')->searchable(),
                TextColumn::make('supplier_invoice_number')->label('N.º de factura')->searchable(),
                TextColumn::make('supplier_invoice_date')->label('Fecha')->date()->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
                TextColumn::make('lines_count')->label('Líneas'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(self::STATUS_LABELS),
                SelectFilter::make('supplier_id')->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('supplier_invoice_date', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Purchase $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'view' => ViewPurchase::route('/{record}'),
            'edit' => EditPurchase::route('/{record}/edit'),
        ];
    }
}
