<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * S4 + S5 + S7: reactiva un usuario desactivado y lo audita.
 */
class ReactivateUserAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    public function execute(User $user, ?int $actorId): User
    {
        return DB::transaction(function () use ($user, $actorId): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $hadManagers = $this->guard->hasManagers();
            $before = UserRules::snapshot($user);

            if ($user->is_active === true) {
                return $user;
            }

            $user->is_active = true;
            $user->save();

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.reactivated',
                'user_id' => $actorId,
                'before' => $before,
                'after' => UserRules::snapshot($user),
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $user;
        });
    }
}
