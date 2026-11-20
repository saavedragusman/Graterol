<?php

declare(strict_types=1);

namespace App\Domain\Settings\Guards;

use App\Domain\Settings\Models\Role;
use App\Models\User;
use InvalidArgumentException;

/**
 * Validaciones y forma de auditoría compartidas por las Actions de usuarios.
 */
class UserRules
{
    public const MIN_PASSWORD = 8;

    /** Nombre recortado, de 1 a 255 caracteres. */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        $length = mb_strlen($name);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('El nombre debe tener entre 1 y 255 caracteres.');
        }

        return $name;
    }

    /** Correo válido y único (sin distinguir mayúsculas). */
    public static function normalizeEmail(string $email, ?User $ignore = null): string
    {
        $email = trim($email);

        if (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo no es válido.');
        }

        $taken = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('Ya existe un usuario con ese correo.');
        }

        return $email;
    }

    public static function assertPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new InvalidArgumentException('La contraseña debe tener al menos '.self::MIN_PASSWORD.' caracteres.');
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function roleIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (Role::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw new InvalidArgumentException('Hay roles que no existen.');
        }

        return $ids;
    }

    /**
     * Forma de auditoría (C15): nunca incluye la contraseña ni su hash.
     *
     * @return array{name: string, email: string, is_active: bool, roles: list<string>}
     */
    public static function snapshot(User $user): array
    {
        /** @var list<string> $roles */
        $roles = $user->roles()->pluck('name')->sort()->values()->all();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'roles' => $roles,
        ];
    }
}
