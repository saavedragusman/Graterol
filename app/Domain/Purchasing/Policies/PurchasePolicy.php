<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Policies;

use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-03/C14: autorización solo por permisos module.action, nunca por rol directo.
 */
class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingView->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingCreate->value);
    }

    public function receive(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingReceive->value);
    }

    public function void(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingVoid->value);
    }
}
