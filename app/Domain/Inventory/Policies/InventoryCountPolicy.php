<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-16/C14: abrir, pesar, confirmar y descartar un conteo físico requieren
 * `inventory.count`; de inicio solo administrador (DEC-05).
 */
class InventoryCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canCount($user);
    }

    public function view(User $user, InventoryCount $count): bool
    {
        return $this->canCount($user);
    }

    public function create(User $user): bool
    {
        return $this->canCount($user);
    }

    public function registerWeight(User $user, InventoryCount $count): bool
    {
        return $this->canCount($user);
    }

    public function confirm(User $user, InventoryCount $count): bool
    {
        return $this->canCount($user);
    }

    public function cancel(User $user, InventoryCount $count): bool
    {
        return $this->canCount($user);
    }

    private function canCount(User $user): bool
    {
        return $user->hasPermission(PermissionKey::InventoryCount->value);
    }
}
