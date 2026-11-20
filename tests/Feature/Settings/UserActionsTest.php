<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\CreateUserAction;
use App\Domain\Settings\Actions\DeactivateUserAction;
use App\Domain\Settings\Actions\DeleteUserAction;
use App\Domain\Settings\Actions\ReactivateUserAction;
use App\Domain\Settings\Actions\ResetUserPasswordAction;
use App\Domain\Settings\Actions\UpdateUserAction;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// S2, S4, S5, S7: Actions de usuarios, transaccionales, auditadas y con guarda anti-bloqueo.

function userSnapshot(): array
{
    return [
        User::query()->orderBy('id')->get(['id', 'name', 'email', 'is_active', 'password'])->toArray(),
        DB::table('role_user')->orderBy('user_id')->orderBy('role_id')->get()->map(fn ($r) => (array) $r)->all(),
        AuditLog::count(),
    ];
}

function roleId(string $name): int
{
    return Role::where('name', $name)->value('id');
}

/** Administrador activo (único gestor de roles salvo que se agreguen más). */
function admin(): User
{
    (new RolePermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->roles()->attach(roleId('administrador'));

    return $admin;
}

it('creates a user with roles and an audit row without password [S2, S5]', function () {
    $admin = admin();

    $user = app(CreateUserAction::class)->execute(
        'Ana Pérez', 'ana@example.test', 'secreto123', [roleId('cajero'), roleId('carnicero')], $admin->id,
    );

    $log = AuditLog::where('action', 'user.created')->sole();

    expect($user->is_active)->toBeTrue()
        ->and(Hash::check('secreto123', $user->password))->toBeTrue()
        ->and($log->auditable_type)->toBe('user')
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->before)->toBeNull()
        ->and($log->after)->toBe([
            'name' => 'Ana Pérez', 'email' => 'ana@example.test', 'is_active' => true,
            'roles' => ['cajero', 'carnicero'],
        ])
        ->and(json_encode($log->after))->not->toContain('secreto123')->not->toContain('password');
});

it('rejects invalid creation without touching anything [S2]', function (string $email, string $password) {
    $admin = admin();
    User::factory()->create(['email' => 'dup@example.test']);
    $before = userSnapshot();

    expect(fn () => app(CreateUserAction::class)->execute('Ana', $email, $password, [roleId('cajero')], $admin->id))
        ->toThrow(InvalidArgumentException::class);

    expect(userSnapshot())->toBe($before);
})->with([
    'duplicate email' => ['DUP@example.test', 'secreto123'],
    'short password' => ['ana@example.test', 'corta'],
    'invalid email' => ['no-es-correo', 'secreto123'],
]);

it('rejects unknown roles on creation [S2]', function () {
    $admin = admin();
    $before = userSnapshot();

    expect(fn () => app(CreateUserAction::class)->execute('Ana', 'ana@example.test', 'secreto123', [999999], $admin->id))
        ->toThrow(InvalidArgumentException::class);

    expect(userSnapshot())->toBe($before);
});

it('updates name, email and roles with before and after audit [S2, S5]', function () {
    $admin = admin();
    $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
    $user->roles()->attach(roleId('cajero'));

    app(UpdateUserAction::class)->execute($user, 'Ana María', 'ana.maria@example.test', [roleId('carnicero')], $admin->id);

    $log = AuditLog::where('action', 'user.roles_updated')->sole();

    expect($user->refresh()->name)->toBe('Ana María')
        ->and($user->roles()->pluck('name')->all())->toBe(['carnicero'])
        ->and($log->auditable_type)->toBe('user')
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->before)->toBe(['name' => 'Ana', 'email' => 'ana@example.test', 'is_active' => true, 'roles' => ['cajero']])
        ->and($log->after)->toBe(['name' => 'Ana María', 'email' => 'ana.maria@example.test', 'is_active' => true, 'roles' => ['carnicero']]);
});

it('does not audit an update without changes [S5]', function () {
    $admin = admin();
    $user = User::factory()->create();

    app(UpdateUserAction::class)->execute($user, $user->name, $user->email, [], $admin->id);

    expect(AuditLog::count())->toBe(0);
});

it('rejects a duplicate email on update without touching anything [S2]', function () {
    $admin = admin();
    User::factory()->create(['email' => 'dup@example.test']);
    $user = User::factory()->create();
    $before = userSnapshot();

    expect(fn () => app(UpdateUserAction::class)->execute($user, 'X', 'dup@example.test', [], $admin->id))
        ->toThrow(InvalidArgumentException::class);

    expect(userSnapshot())->toBe($before);
});

it('rejects removing the administrador role from the last role manager [S7]', function () {
    $admin = admin();
    $other = User::factory()->create();
    $before = userSnapshot();

    expect(fn () => app(UpdateUserAction::class)->execute($admin, $admin->name, $admin->email, [], $other->id))
        ->toThrow(RuntimeException::class, 'Debe quedar al menos un usuario activo con permiso para gestionar roles.');

    expect(userSnapshot())->toBe($before);
});

it('deactivates and reactivates a user with audit [S4, S5]', function () {
    $admin = admin();
    $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
    $user->roles()->attach(roleId('cajero'));

    app(DeactivateUserAction::class)->execute($user, $admin->id);

    $off = AuditLog::where('action', 'user.deactivated')->sole();
    expect($user->refresh()->is_active)->toBeFalse()
        ->and($off->before['is_active'])->toBeTrue()
        ->and($off->after['is_active'])->toBeFalse()
        ->and($off->after['roles'])->toBe(['cajero']);

    app(ReactivateUserAction::class)->execute($user, $admin->id);

    $on = AuditLog::where('action', 'user.reactivated')->sole();
    expect($user->refresh()->is_active)->toBeTrue()
        ->and($on->before['is_active'])->toBeFalse()
        ->and($on->after['is_active'])->toBeTrue()
        ->and($on->user_id)->toBe($admin->id);
});

it('rejects deactivating oneself or the last active role manager [S4, S7]', function () {
    $admin = admin();
    $second = User::factory()->create();
    $second->roles()->attach(roleId('administrador'));
    $other = User::factory()->create();
    $before = userSnapshot();

    // Uno mismo.
    expect(fn () => app(DeactivateUserAction::class)->execute($admin, $admin->id))
        ->toThrow(RuntimeException::class, 'No puedes desactivarte a ti mismo.');

    // Desactivar al segundo gestor es válido; después el primero es el último.
    app(DeactivateUserAction::class)->execute($second, $other->id);
    $mid = userSnapshot();

    expect(fn () => app(DeactivateUserAction::class)->execute($admin, $other->id))
        ->toThrow(RuntimeException::class, 'Debe quedar al menos un usuario activo con permiso para gestionar roles.');

    expect(userSnapshot())->toBe($mid)->and($mid)->not->toBe($before);
});

it('resets the password with audit that never includes it [S2, S5]', function () {
    $admin = admin();
    $user = User::factory()->create();
    $oldHash = $user->password;

    app(ResetUserPasswordAction::class)->execute($user, 'nueva-clave-1', $admin->id);

    $log = AuditLog::where('action', 'user.password_reset')->sole();

    expect(Hash::check('nueva-clave-1', $user->refresh()->password))->toBeTrue()
        ->and($user->password)->not->toBe($oldHash)
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and(json_encode([$log->before, $log->after]))->not->toContain('nueva-clave-1')->not->toContain($user->password)->not->toContain($oldHash);
});

it('rejects a short password on reset without touching anything [S2]', function () {
    $admin = admin();
    $user = User::factory()->create();
    $before = userSnapshot();

    expect(fn () => app(ResetUserPasswordAction::class)->execute($user, 'corta', $admin->id))
        ->toThrow(InvalidArgumentException::class);

    expect(userSnapshot())->toBe($before);
});

it('deletes a user without history, ignoring role_user, and audits it [S4, S5]', function () {
    $admin = admin();
    $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
    $user->roles()->attach(roleId('cajero'));

    app(DeleteUserAction::class)->execute($user, $admin->id);

    $log = AuditLog::where('action', 'user.deleted')->sole();

    expect(User::find($user->id))->toBeNull()
        ->and(DB::table('role_user')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->before)->toBe(['name' => 'Ana', 'email' => 'ana@example.test', 'is_active' => true, 'roles' => ['cajero']])
        ->and($log->after)->toBeNull();
});

it('rejects deleting a user with history and suggests deactivation [S4]', function () {
    $admin = admin();
    $user = User::factory()->create();
    $user->roles()->attach(roleId('cajero'));
    AuditLog::create(['auditable_type' => 'setting', 'auditable_id' => 1, 'action' => 'updated', 'user_id' => $user->id]);
    $before = userSnapshot();

    expect(fn () => app(DeleteUserAction::class)->execute($user, $admin->id))
        ->toThrow(RuntimeException::class, 'desactívalo');

    expect(userSnapshot())->toBe($before);
});

it('rejects deleting oneself or the last role manager [S4, S7]', function () {
    $admin = admin();
    $other = User::factory()->create();
    $before = userSnapshot();

    expect(fn () => app(DeleteUserAction::class)->execute($admin, $admin->id))
        ->toThrow(RuntimeException::class, 'No puedes borrarte a ti mismo.');
    expect(fn () => app(DeleteUserAction::class)->execute($admin, $other->id))
        ->toThrow(RuntimeException::class, 'Debe quedar al menos un usuario activo con permiso para gestionar roles.');

    expect(userSnapshot())->toBe($before);
});
