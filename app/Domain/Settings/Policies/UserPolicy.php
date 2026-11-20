<?php

declare(strict_types=1);

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * C14: usuarios se gestionan con `users.manage`. Nadie se borra ni se desactiva a sí mismo.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->manages($user);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->manages($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->hasPermission(PermissionKey::UsersManage->value);
    }
}
