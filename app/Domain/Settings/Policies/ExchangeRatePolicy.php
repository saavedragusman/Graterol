<?php

declare(strict_types=1);

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\ExchangeRate;
use App\Domain\Settings\PermissionKey;
use App\Models\User;

/**
 * C14/S5/S6: la tasa BCV se consulta y se registra con `rates.manage`;
 * la tabla es de solo inserción, así que nadie edita ni borra.
 */
class ExchangeRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, ExchangeRate $rate): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, ExchangeRate $rate): bool
    {
        return false;
    }

    public function delete(User $user, ExchangeRate $rate): bool
    {
        return false;
    }

    public function restore(User $user, ExchangeRate $rate): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExchangeRate $rate): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->hasPermission(PermissionKey::RatesManage->value);
    }
}
