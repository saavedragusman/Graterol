<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Product;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-04/C14: solo administración gestiona el catálogo (configuración).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CatalogManage->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission(PermissionKey::CatalogManage->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::CatalogManage->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission(PermissionKey::CatalogManage->value);
    }
}
