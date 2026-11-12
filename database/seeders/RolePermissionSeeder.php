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
 * Administrador tiene todos los permisos; cajero, POS y su propio cierre;
 * carnicero, recepción y despiece. Anular y devolver solo los tiene administrador.
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

            $administrador = Role::firstOrCreate(['name' => 'administrador']);
            $administrador->permissions()->sync(
                collect($permissions)->pluck('id')->all()
            );

            $cajero = Role::firstOrCreate(['name' => 'cajero']);
            $cajero->permissions()->sync([
                $permissions[PermissionKey::PosSell->value]->id,
                $permissions[PermissionKey::PosCloseOwn->value]->id,
            ]);

            $carnicero = Role::firstOrCreate(['name' => 'carnicero']);
            $carnicero->permissions()->sync([
                $permissions[PermissionKey::PurchasingReceive->value]->id,
                $permissions[PermissionKey::ButcheryProcess->value]->id,
            ]);
        });
    }
}
