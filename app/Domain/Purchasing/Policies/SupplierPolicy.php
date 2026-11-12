<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Policies;

use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-03/C14: autorización solo por permisos module.action, nunca por rol directo.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingView->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingManageSuppliers->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(PermissionKey::PurchasingManageSuppliers->value);
    }
}
