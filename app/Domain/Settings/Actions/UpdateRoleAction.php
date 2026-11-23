<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\RoleRules;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * S3 + S5 + S7: cambia nombre y permisos de un rol y lo audita.
 * El rol de sistema (administrador) puede renombrarse, pero no perder permisos.
 */
class UpdateRoleAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    /**
     * @param  list<string>  $permissions  nombres module.action
     */
    public function execute(Role $role, string $name, array $permissions, ?int $userId): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions, $userId): Role {
            // Bloqueo de fila: dos ediciones simultáneas no se pisan.
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);

            $hadManagers = $this->guard->hasManagers();
            $name = RoleRules::normalizeName($name, $role);
            $ids = RoleRules::permissionIds($permissions);

            if ($role->is_system && count($ids) < Permission::query()->count()) {
                throw new RuntimeException('El rol administrador conserva todos los permisos.');
            }

            $before = RoleRules::snapshot($role);

            $role->update(['name' => $name]);
            $role->permissions()->sync($ids);

            $after = RoleRules::snapshot($role->refresh());

            if ($before === $after) {
                return $role;
            }

            AuditLog::create([
                'auditable_type' => 'role',
                'auditable_id' => $role->id,
                'action' => 'role.updated',
                'user_id' => $userId,
                'before' => $before,
                'after' => $after,
            ]);

            $this->guard->assertAfterChange($hadManagers);

            return $role;
        });
    }
}
