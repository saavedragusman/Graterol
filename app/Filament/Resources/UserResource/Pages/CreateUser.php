<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Domain\Settings\Actions\CreateUserAction;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * C3: la regla vive en CreateUserAction; aquí solo se orquesta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateUserAction::class)->execute(
                name: (string) ($data['name'] ?? ''),
                email: (string) ($data['email'] ?? ''),
                password: (string) ($data['password'] ?? ''),
                roleIds: array_map('intval', (array) ($data['role_ids'] ?? [])),
                actorId: auth()->id(),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()->title('No se pudo crear el usuario')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
