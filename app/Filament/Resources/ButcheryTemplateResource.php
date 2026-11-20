<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductType;
use App\Filament\Resources\ButcheryTemplateResource\Pages\CreateButcheryTemplate;
use App\Filament\Resources\ButcheryTemplateResource\Pages\EditButcheryTemplate;
use App\Filament\Resources\ButcheryTemplateResource\Pages\ListButcheryTemplates;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * BR-08: plantillas de despiece. Solo administración gestiona (configuración,
 * DEC-02); el carnicero las ve congeladas dentro de cada orden, no aquí.
 * Sin pantalla de referencia dibujada para este recurso (docs/design.md §5):
 * se construye con los patrones de §3/§4, igual que SupplierResource en M2.
 * Autorización: ButcheryTemplatePolicy (butchery.manage-templates).
 */
class ButcheryTemplateResource extends Resource
{
    protected static ?string $model = ButcheryTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Despiece';

    protected static ?string $modelLabel = 'plantilla de despiece';

    protected static ?string $pluralModelLabel = 'plantillas de despiece';

    // Filament capitaliza cada palabra del plural; la etiqueta del menú va en español normal.
    protected static ?string $navigationLabel = 'Plantillas de despiece';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plantilla')->columns(2)->schema([
                Select::make('raw_material_product_id')
                    ->label('Materia prima')
                    ->options(fn (): array => Product::query()
                        ->where('product_type', ProductType::RawMaterial->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    // DEC-02: crear una plantilla nueva desactiva la anterior de
                    // la misma materia prima; no tiene sentido cambiarla al editar.
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),
                TextInput::make('name')->label('Nombre')->required()->maxLength(150),
                TextInput::make('expected_shrinkage_percent')
                    ->label('Merma esperada (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
            ]),
            Section::make('Cortes')->schema([
                // BR-08: % estándar por corte + merma esperada deben sumar 100;
                // la Action valida la suma exacta (tolerancia ±0,01).
                Repeater::make('lines')
                    ->label('Cortes')
                    ->minItems(1)
                    ->required()
                    ->columns(2)
                    ->schema([
                        Select::make('cut_product_id')
                            ->label('Corte')
                            ->options(fn (): array => Product::query()
                                ->where('product_type', ProductType::Derived->value)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('expected_yield_percent')
                            ->label('% estándar')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('rawMaterial')->withCount('lines'))
            ->columns([
                TextColumn::make('rawMaterial.name')->label('Materia prima')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('expected_shrinkage_percent')->label('Merma esperada (%)'),
                TextColumn::make('lines_count')->label('Cortes'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->defaultSort('raw_material_product_id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListButcheryTemplates::route('/'),
            'create' => CreateButcheryTemplate::route('/create'),
            'edit' => EditButcheryTemplate::route('/{record}/edit'),
        ];
    }
}
