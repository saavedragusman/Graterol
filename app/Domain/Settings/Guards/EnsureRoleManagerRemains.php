<?php

declare(strict_types=1);

namespace App\Domain\Settings\Guards;

use App\Domain\Settings\PermissionKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * S7: nunca quedarse afuera. Ningún cambio puede dejar el sistema sin al menos
 * un usuario activo con `roles.manage`.
 *
 * Uso: dentro de la transacción de la Action, `$had = $guard->hasManagers()` antes
 * de aplicar el cambio y `$guard->assertAfterChange($had)` al final. Si lanza, la
 * transacción se revierte (roles, permisos, asignaciones y auditoría quedan igual).
 * Solo rechaza cambios que *quiten* al último gestor; si no había ninguno de
 * antemano (instalación sin usuarios), el cambio no empeora nada y se permite.
 * Sirve igual para cambios de permisos de un rol, asignación de roles y desactivación.
 */
class EnsureRoleManagerRemains
{
    public const MESSAGE = 'Debe quedar al menos un usuario activo con permiso para gestionar roles.';

    public function hasManagers(): bool
    {
        return $this->activeRoleManagers()->exists();
    }

    public function assertAfterChange(bool $hadManagers): void
    {
        if ($hadManagers && ! $this->hasManagers()) {
            throw new RuntimeException(self::MESSAGE);
        }
    }

    /**
     * @return Builder<User>
     */
    private function activeRoleManagers(): Builder
    {
        $query = User::query()->whereHas(
            'roles.permissions',
            fn ($permissions) => $permissions->where('name', PermissionKey::RolesManage->value)
        );

        return $this->onlyActive($query);
    }

    /**
     * Única condición de "usuario activo".
     * TODO(T3): al existir `users.is_active`, filtrar aquí `where('is_active', true)`;
     * hoy todos los usuarios cuentan como activos.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function onlyActive(Builder $query): Builder
    {
        return $query;
    }
}
