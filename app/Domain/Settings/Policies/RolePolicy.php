<?php

declare(strict_types=1);

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * C14: roles y permisos se gestionan con `roles.manage`. El rol de sistema no se borra.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RolesManage->value);
    }
}
