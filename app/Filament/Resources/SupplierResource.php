<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Purchasing\Models\Supplier;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * M2: CRUD simple de proveedores. No hay regla de negocio al crearlos,
 * por eso no hay Action; desactivar es poner `is_active` en falso.
 * Autorización: SupplierPolicy (purchasing.view / purchasing.manage-suppliers).
 */
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'proveedor';

    protected static ?string $pluralModelLabel = 'proveedores';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('tax_id')->label('RIF')->maxLength(50),
            TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
            Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('tax_id')->label('RIF')->searchable(),
                TextColumn::make('phone')->label('Teléfono'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->visible(fn (Supplier $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
