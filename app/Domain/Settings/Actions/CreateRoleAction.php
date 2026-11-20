<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\RoleRules;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * S3 + S5: crea un rol con sus permisos y lo audita. Todo o nada.
 */
class CreateRoleAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    /**
     * @param  list<string>  $permissions  nombres module.action
     */
    public function execute(string $name, array $permissions, ?int $userId): Role
    {
        return DB::transaction(function () use ($name, $permissions, $userId): Role {
            $hadManagers = $this->guard->hasManagers();
            $name = RoleRules::normalizeName($name);
            $ids = RoleRules::permissionIds($permissions);

            $role = Role::create(['name' => $name]);
            $role->permissions()->sync($ids);

            AuditLog::create([
                'auditable_type' => 'role',
                'auditable_id' => $role->id,
                'action' => 'role.created',
                'user_id' => $userId,
                'before' => null,
                'after' => RoleRules::snapshot($role),
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $role->refresh();
        });
    }
}
