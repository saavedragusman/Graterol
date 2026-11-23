<?php

declare(strict_types=1);

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * C14/C15: la auditoría se ve con `audit.view` y es inmutable para todos.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::AuditView->value);
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasPermission(PermissionKey::AuditView->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function restore(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
