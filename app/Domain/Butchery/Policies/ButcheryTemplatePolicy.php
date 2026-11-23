<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Policies;

use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * BR-03/C14: solo administración gestiona plantillas (configuración), igual
 * que `purchasing.manage-suppliers` en M2.
 */
class ButcheryTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryManageTemplates->value);
    }

    public function view(User $user, ButcheryTemplate $template): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryManageTemplates->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryManageTemplates->value);
    }

    public function update(User $user, ButcheryTemplate $template): bool
    {
        return $user->hasPermission(PermissionKey::ButcheryManageTemplates->value);
    }
}
