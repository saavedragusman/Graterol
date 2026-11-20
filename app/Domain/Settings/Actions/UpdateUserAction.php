<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * S2 + S5 + S7: cambia nombre, correo y roles de un usuario y lo audita.
 */
class UpdateUserAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function execute(User $user, string $name, string $email, array $roleIds, ?int $actorId): User
    {
        return DB::transaction(function () use ($user, $name, $email, $roleIds, $actorId): User {
            // Bloqueo de fila: dos ediciones simultáneas no se pisan.
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $hadManagers = $this->guard->hasManagers();
            $name = UserRules::normalizeName($name);
            $email = UserRules::normalizeEmail($email, $user);
            $ids = UserRules::roleIds($roleIds);

            $before = UserRules::snapshot($user);

            $user->update(['name' => $name, 'email' => $email]);
            $user->roles()->sync($ids);

            $after = UserRules::snapshot($user->refresh());

            if ($before === $after) {
                return $user;
            }

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.roles_updated',
                'user_id' => $actorId,
                'before' => $before,
                'after' => $after,
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $user;
        });
    }
}
