<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\UserHistory;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * S4 + S5 + S7: borra un usuario solo si no tiene historial en ninguna tabla.
 */
class DeleteUserAction
{
    public function __construct(
        private readonly EnsureRoleManagerRemains $guard,
        private readonly UserHistory $history,
    ) {}

    public function execute(User $user, ?int $actorId): void
    {
        DB::transaction(function () use ($user, $actorId): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->id === $actorId) {
                throw new RuntimeException('No puedes borrarte a ti mismo.');
            }

            $hadManagers = $this->guard->hasManagers();

            if ($this->history->exists($user)) {
                throw new RuntimeException('El usuario tiene historial y no se puede borrar; desactívalo en su lugar.');
            }

            $before = UserRules::snapshot($user);

            $user->roles()->detach();
            $user->delete();

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.deleted',
                'user_id' => $actorId,
                'before' => $before,
                'after' => null,
            ]);

            $this->guard->assertAfterChange($hadManagers);
        });
    }
}
