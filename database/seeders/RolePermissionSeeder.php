<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BR-03: roles iniciales como conjuntos de permisos module.action.
 * Administrador (is_system) tiene todos los permisos y recibe los nuevos; cajero, POS y su propio cierre;
 * carnicero, recepción y despiece.
 * No destructivo: nunca pisa lo editado desde la pantalla de roles. Anular y devolver solo los tiene administrador.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $permissions = [];

            foreach (PermissionKey::cases() as $key) {
                $permissions[$key->value] = Permission::firstOrCreate(['name' => $key->value]);
            }

            // El administrador se reconoce por is_system, no por el nombre (puede renombrarse).
            $administrador = Role::where('is_system', true)->first();

            if ($administrador === null) {
                $administrador = Role::firstOrCreate(['name' => 'administrador']);
                $administrador->forceFill(['is_system' => true])->save();
            }

            // Nunca quita permisos: solo agrega los que le falten (S3).
            $administrador->permissions()->syncWithoutDetaching(
                Permission::query()->pluck('id')->all()
            );

            // Los demás roles iniciales se crean solo si no existen: lo editado en pantalla se respeta.
            $this->createInitialRole('cajero', [
                $permissions[PermissionKey::PosSell->value]->id,
                $permissions[PermissionKey::PosCloseOwn->value]->id,
            ]);

            $this->createInitialRole('carnicero', [
                $permissions[PermissionKey::PurchasingView->value]->id,
                $permissions[PermissionKey::PurchasingReceive->value]->id,
                $permissions[PermissionKey::ButcheryProcess->value]->id,
            ]);
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function createInitialRole(string $name, array $permissionIds): void
    {
        $role = Role::firstOrCreate(['name' => $name]);

        if ($role->wasRecentlyCreated) {
            $role->permissions()->sync($permissionIds);
        }
    }
}
