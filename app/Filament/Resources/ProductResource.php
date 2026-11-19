<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\PriceList;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Support\ValueObjects\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * M1 (BR-04, BR-05): pantalla del catálogo de productos. La creación pasa por
 * CreateProductAction y los precios por SetProductPriceAction; el IVA lo decide
 * la categoría, no el formulario. Tipo, unidad y categoría no se editan después
 * de crear: cambiarlos alteraría el IVA y el historial de lotes.
 * Autorización: ProductPolicy (catalog.manage).
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static ?string $recordTitleAttribute = 'name';

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'raw_material' => 'Materia prima',
        'derived' => 'Derivado (corte)',
        'resale' => 'Reventa',
    ];

    /** @var array<string, string> */
    public const UNIT_LABELS = ['kg' => 'Kilogramo', 'piece' => 'Pieza'];

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        'natural_meat' => 'Carne natural (exenta de IVA)',
        'charcuteria' => 'Charcutería',
        'other' => 'Otro',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Producto')->columns(2)->schema([
                TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                Select::make('product_type')->label('Tipo')
                    ->options(self::TYPE_LABELS)
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Los cortes del despiece son de tipo «Derivado».'),
                Select::make('unit')->label('Unidad')
                    ->options(self::UNIT_LABELS)
                    ->default(Unit::Kilogram->value)
                    ->required()
                    ->disabledOn('edit'),
                Select::make('product_category')->label('Categoría')
                    ->options(self::CATEGORY_LABELS)
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('La categoría define el IVA: carnes naturales exentas; el resto, a la tasa configurada.'),
                Toggle::make('active')->label('Activo')->default(true)->visibleOn('edit'),
            ]),
            Section::make('Precios')
                ->description('El precio detal es el que usa el costeo del despiece (DEC-01). Se puede dejar vacío y configurarlo después.')
                ->columns(3)
                ->schema([
                    Select::make('price_currency')->label('Moneda')
                        ->options(['USD' => '$ (USD)', 'VES' => 'Bs (VES)'])
                        ->default('USD'),
                    TextInput::make('detail_price')->label('Precio detal')->numeric()->minValue(0)->step('0.0001'),
                    TextInput::make('wholesale_price')->label('Precio mayor')->numeric()->minValue(0)->step('0.0001'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('product_type')->label('Tipo')
                    ->formatStateUsing(fn (ProductType $state): string => self::TYPE_LABELS[$state->value]),
                TextColumn::make('product_category')->label('Categoría')
                    ->formatStateUsing(fn (ProductCategory $state): string => self::CATEGORY_LABELS[$state->value]),
                TextColumn::make('unit')->label('Unidad')
                    ->formatStateUsing(fn (Unit $state): string => self::UNIT_LABELS[$state->value]),
                IconColumn::make('is_tax_exempt')->label('Exento')->boolean(),
                TextColumn::make('detail_price')->label('Precio detal')
                    ->state(fn (Product $record): ?string => $record->priceFor(PriceList::Detail->value)?->getRawOriginal('amount'))
                    ->placeholder('Sin precio')
                    ->formatStateUsing(fn (?string $state, Product $record): string => Money::display($state, (string) $record->priceFor(PriceList::Detail->value)?->currency)),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('product_type')->label('Tipo')->options(self::TYPE_LABELS),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->visible(fn (Product $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
