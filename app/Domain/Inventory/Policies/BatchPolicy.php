<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-14/BR-15/C14: ver el inventario y el kardex (`inventory.view`) y registrar
 * mermas (`inventory.register-shrinkage`); de inicio solo administrador (DEC-05).
 */
class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::InventoryView->value);
    }

    public function view(User $user, Batch $batch): bool
    {
        return $user->hasPermission(PermissionKey::InventoryView->value);
    }

    public function registerShrinkage(User $user, Batch $batch): bool
    {
        return $user->hasPermission(PermissionKey::InventoryRegisterShrinkage->value);
    }
}
