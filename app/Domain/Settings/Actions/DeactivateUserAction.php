<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * S4 + S5 + S7: desactiva un usuario (no entra más, su historial queda) y lo audita.
 */
class DeactivateUserAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    public function execute(User $user, ?int $actorId): User
    {
        return DB::transaction(function () use ($user, $actorId): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->id === $actorId) {
                throw new RuntimeException('No puedes desactivarte a ti mismo.');
            }

            $hadManagers = $this->guard->hasManagers();
            $before = UserRules::snapshot($user);

            if ($user->is_active === false) {
                return $user;
            }

            $user->is_active = false;
            $user->save();

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.deactivated',
                'user_id' => $actorId,
                'before' => $before,
                'after' => UserRules::snapshot($user),
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $user;
        });
    }
}
