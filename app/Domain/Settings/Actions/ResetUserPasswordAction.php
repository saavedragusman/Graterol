<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Guards\UserRules;
use App\Domain\Settings\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * S2 + S5: fija una contraseña nueva dada por el administrador. La auditoría
 * registra el hecho, nunca la contraseña ni su hash.
 */
class ResetUserPasswordAction
{
    public function execute(User $user, string $password, ?int $actorId): User
    {
        return DB::transaction(function () use ($user, $password, $actorId): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            UserRules::assertPassword($password);

            $user->password = $password; // el cast 'hashed' la cifra
            $user->save();

            $snapshot = UserRules::snapshot($user);

            AuditLog::create([
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'action' => 'user.password_reset',
                'user_id' => $actorId,
                'before' => $snapshot,
                'after' => $snapshot,
            ]);

            return $user;
        });
    }
}
