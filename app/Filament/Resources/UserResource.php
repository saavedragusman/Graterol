<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Settings\Actions\DeactivateUserAction;
use App\Domain\Settings\Actions\DeleteUserAction;
use App\Domain\Settings\Actions\ReactivateUserAction;
use App\Domain\Settings\Actions\ResetUserPasswordAction;
use App\Domain\Settings\Guards\UserHistory;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;
use UnitEnum;

/**
 * S2 + S4: pantalla «Configuración › Usuarios». La regla vive en las Actions de
 * usuarios; la autorización, en UserPolicy (users.manage).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('email')->label('Correo')->email()->required()->maxLength(255),
            // La contraseña solo se fija al crear; después se restablece con su acción.
            TextInput::make('password')->label('Contraseña')->password()->revealable()
                ->required()->minLength(UserRules::MIN_PASSWORD)->confirmed()
                ->visibleOn('create'),
            TextInput::make('password_confirmation')->label('Confirmar contraseña')->password()->revealable()
                ->required()->dehydrated(false)
                ->visibleOn('create'),
            CheckboxList::make('role_ids')->label('Roles')
                ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('is_active')->label('Activo')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Activo')
                    ->trueLabel('Activos')->falseLabel('Inactivos')->placeholder('Todos'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')->label('Desactivar')->icon('heroicon-o-no-symbol')->color('warning')
                    ->authorize('deactivate')
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription('El usuario no podrá entrar al panel ni a la PWA, pero su historial se conserva.')
                    ->action(fn (User $record) => self::run(
                        fn () => app(DeactivateUserAction::class)->execute($record, auth()->id()),
                        'No se pudo desactivar el usuario',
                    )),
                Action::make('reactivate')->label('Reactivar')->icon('heroicon-o-check-circle')->color('success')
                    ->authorize('update')
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => self::run(
                        fn () => app(ReactivateUserAction::class)->execute($record, auth()->id()),
                        'No se pudo reactivar el usuario',
                    )),
                Action::make('resetPassword')->label('Restablecer contraseña')->icon('heroicon-o-key')
                    ->authorize('update')
                    ->modalHeading('Restablecer contraseña')
                    ->schema([
                        TextInput::make('password')->label('Nueva contraseña')->password()->revealable()
                            ->required()->minLength(UserRules::MIN_PASSWORD)->confirmed(),
                        TextInput::make('password_confirmation')->label('Confirmar contraseña')->password()->revealable()
                            ->required(),
                    ])
                    ->action(fn (User $record, array $data) => self::run(
                        fn () => app(ResetUserPasswordAction::class)->execute($record, (string) $data['password'], auth()->id()),
                        'No se pudo restablecer la contraseña',
                    )),
                // Solo se puede borrar un usuario sin historial (S4); si no, se desactiva.
                DeleteAction::make()
                    ->disabled(fn (User $record): bool => app(UserHistory::class)->exists($record))
                    ->tooltip(fn (User $record): ?string => app(UserHistory::class)->exists($record)
                        ? 'Tiene historial: solo se puede desactivar.'
                        : null)
                    ->using(fn (User $record): bool => self::run(
                        fn () => app(DeleteUserAction::class)->execute($record, auth()->id()),
                        'No se pudo borrar el usuario',
                    )),
            ]);
    }

    /**
     * Ejecuta una Action de dominio; si rechaza, avisa y detiene la acción de Filament.
     *
     * @param  callable(): mixed  $callback
     */
    private static function run(callable $callback, string $title): bool
    {
        try {
            $callback();
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()->title($title)->body($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
