<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * S3: pantalla «Configuración › Roles». La regla vive en las Actions de roles;
 * la autorización, en RolePolicy (roles.manage).
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $recordTitleAttribute = 'name';

    /** Clave del grupo en el formulario (`permission_groups.<clave>`). */
    public static function groupKey(string $module): string
    {
        return Str::slug($module);
    }

    /**
     * Permisos agrupados por módulo, en el orden del enum.
     *
     * @return array<string, array<string, string>> módulo => [permiso => etiqueta]
     */
    public static function groupedOptions(): array
    {
        $groups = [];

        foreach (PermissionKey::cases() as $key) {
            $groups[$key->module()][$key->value] = $key->label();
        }

        return $groups;
    }

    /**
     * Estado inicial del formulario: los permisos del rol repartidos por grupo
     * (todos marcados para el rol de sistema).
     *
     * @return array<string, list<string>>
     */
    public static function groupedState(Role $role): array
    {
        $owned = $role->permissions()->pluck('name')->all();
        $state = [];

        foreach (self::groupedOptions() as $module => $options) {
            $names = array_keys($options);
            $state[self::groupKey($module)] = array_values(
                $role->is_system ? $names : array_intersect($names, $owned)
            );
        }

        return $state;
    }

    /**
     * Lista plana de permisos a partir del estado del formulario.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function flattenGroups(array $data): array
    {
        $names = [];

        foreach ((array) ($data['permission_groups'] ?? []) as $selected) {
            foreach ((array) $selected as $name) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Todos los permisos de PermissionKey (el rol de sistema los conserva siempre).
     *
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        return array_map(fn (PermissionKey $key): string => $key->value, PermissionKey::cases());
    }

    public static function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (self::groupedOptions() as $module => $options) {
            $sections[] = Section::make($module)
                ->schema([
                    CheckboxList::make('permission_groups.'.self::groupKey($module))
                        ->hiddenLabel()
                        ->options($options)
                        ->columns(2)
                        // El rol de sistema siempre tiene todos los permisos (S3).
                        ->disabled(fn (?Role $record): bool => (bool) $record?->is_system),
                ])
                ->collapsible();
        }

        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->minLength(1)->maxLength(100),
            Section::make('Permisos')
                ->description('Marca lo que este rol puede hacer. El rol de sistema siempre conserva todos.')
                ->schema($sections),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['users', 'permissions']))
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                IconColumn::make('is_system')->label('Sistema')->boolean(),
                TextColumn::make('users_count')->label('Usuarios')->sortable(),
                TextColumn::make('permissions_count')->label('Permisos')->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
