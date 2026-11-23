<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\EnsureRoleManagerRemains;
use App\Domain\Settings\Guards\RoleRules;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * S3 + S5: borra un rol. Rechaza el rol de sistema y los roles con usuarios.
 */
class DeleteRoleAction
{
    public function __construct(private readonly EnsureRoleManagerRemains $guard) {}

    public function execute(Role $role, ?int $userId): void
    {
        DB::transaction(function () use ($role, $userId): void {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);

            $hadManagers = $this->guard->hasManagers();

            if ($role->is_system) {
                throw new RuntimeException('El rol administrador no se puede borrar.');
            }

            if ($role->users()->exists()) {
                throw new RuntimeException('No se puede borrar un rol que tiene usuarios asignados.');
            }

            $before = RoleRules::snapshot($role);

            $role->permissions()->detach();
            $role->delete();

            AuditLog::create([
                'auditable_type' => 'role',
                'auditable_id' => $role->id,
                'action' => 'role.deleted',
                'user_id' => $userId,
                'before' => $before,
                'after' => null,
            ]);

            $this->guard->assertAfterChange($hadManagers);
        });
    }
}
