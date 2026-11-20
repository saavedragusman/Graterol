<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Domain\Settings\Actions\CreateRoleAction;
use App\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * C3: la regla vive en CreateRoleAction; aquí solo se orquesta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateRoleAction::class)->execute(
                name: (string) ($data['name'] ?? ''),
                permissions: RoleResource::flattenGroups($data),
                userId: auth()->id(),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()->title('No se pudo crear el rol')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
