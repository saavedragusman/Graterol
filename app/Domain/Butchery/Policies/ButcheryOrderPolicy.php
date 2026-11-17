<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Policies;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-03/C14: el carnicero abre, pesa y cierra (`butchery.process`); anular
 * requiere un permiso propio, de inicio solo administrador (`butchery.void`).
 */
class ButcheryOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryProcess->value)
            || $user->hasPermission(PermissionKey::ButcheryManageTemplates->value);
    }

    public function view(User $user, ButcheryOrder $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryProcess->value);
    }

    public function registerWeight(User $user, ButcheryOrder $order): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryProcess->value);
    }

    public function close(User $user, ButcheryOrder $order): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryProcess->value);
    }

    public function void(User $user, ButcheryOrder $order): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryVoid->value);
    }
}
