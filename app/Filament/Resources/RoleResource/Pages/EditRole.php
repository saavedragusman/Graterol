<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Domain\Settings\Actions\DeleteRoleAction;
use App\Domain\Settings\Actions\UpdateRoleAction;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Role $record): bool => ! $record->is_system)
                ->using(function (Role $record): bool {
                    try {
                        app(DeleteRoleAction::class)->execute($record, auth()->id());
                    } catch (RuntimeException $e) {
                        Notification::make()->title('No se pudo borrar el rol')->body($e->getMessage())->danger()->send();

                        throw new Halt;
                    }

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $data['permission_groups'] = RoleResource::groupedState($role);

        return $data;
    }

    /**
     * C3: la regla vive en UpdateRoleAction; aquí solo se orquesta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        // Los campos deshabilitados no se envían: el rol de sistema conserva todos.
        $permissions = $record->is_system
            ? RoleResource::allPermissionNames()
            : RoleResource::flattenGroups($data);

        try {
            return app(UpdateRoleAction::class)->execute(
                role: $record,
                name: (string) ($data['name'] ?? ''),
                permissions: $permissions,
                userId: auth()->id(),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()->title('No se pudo guardar el rol')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
