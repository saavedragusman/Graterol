<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * S2 + S5: crea un usuario activo con sus roles y lo audita (sin la contraseña).
 */
class CreateUserAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function execute(string $name, string $email, string $password, array $roleIds, ?int $actorId): User
    {
        return DB::transaction(function () use ($name, $email, $password, $roleIds, $actorId): User {
            $hadManagers = $this->guard->hasManagers();
            $name = UserRules::normalizeName($name);
            $email = UserRules::normalizeEmail($email);
            UserRules::assertPassword($password);
            $ids = UserRules::roleIds($roleIds);

            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->is_active = true;
            $user->save();
            $user->roles()->sync($ids);

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.created',
                'user_id' => $actorId,
                'before' => null,
                'after' => UserRules::snapshot($user),
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $user->refresh();
        });
    }
}
