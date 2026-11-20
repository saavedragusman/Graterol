<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Domain\Settings\Actions\UpdateUserAction;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $data['role_ids'] = $user->roles()->pluck('roles.id')->map(fn ($id): int => (int) $id)->all();

        return $data;
    }

    /**
     * C3: la regla vive en UpdateUserAction; aquí solo se orquesta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        try {
            return app(UpdateUserAction::class)->execute(
                user: $record,
                name: (string) ($data['name'] ?? ''),
                email: (string) ($data['email'] ?? ''),
                roleIds: array_map('intval', (array) ($data['role_ids'] ?? [])),
                actorId: auth()->id(),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()->title('No se pudo guardar el usuario')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
