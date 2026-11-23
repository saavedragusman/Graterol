<?php

declare(strict_types=1);

namespace App\Domain\Settings\Guards;

use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use InvalidArgumentException;

/**
 * Validaciones y forma de auditoría compartidas por las Actions de roles.
 */
class RoleRules
{
    /** Nombre recortado, de 1 a 100 caracteres y único (sin distinguir mayúsculas). */
    public static function normalizeName(string $name, ?Role $ignore = null): string
    {
        $name = trim($name);
        $length = mb_strlen($name);

        if ($length < 1 || $length > 100) {
            throw new InvalidArgumentException('El nombre del rol debe tener entre 1 y 100 caracteres.');
        }

        $taken = Role::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('Ya existe un rol con ese nombre.');
        }

        return $name;
    }

    /**
     * @param  list<string>  $names
     * @return list<int> ids de los permisos
     */
    public static function permissionIds(array $names): array
    {
        $names = array_values(array_unique($names));
        $ids = Permission::query()->whereIn('name', $names)->pluck('id', 'name');

        if ($ids->count() !== count($names)) {
            throw new InvalidArgumentException('Hay permisos que no existen.');
        }

        return $ids->values()->all();
    }

    /**
     * Forma de auditoría (C15): nombre y permisos ordenados.
     *
     * @return array{name: string, permissions: list<string>}
     */
    public static function snapshot(Role $role): array
    {
        /** @var list<string> $permissions */
        $permissions = $role->permissions()->pluck('name')->sort()->values()->all();

        return ['name' => $role->name, 'permissions' => $permissions];
    }
}
