<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\CreateRoleAction;
use App\Domain\Settings\Actions\DeleteRoleAction;
use App\Domain\Settings\Actions\UpdateRoleAction;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// S3, S5, S7: Actions de roles, transaccionales, auditadas y con guarda anti-bloqueo.

function snapshot(): array
{
    return [
        Role::query()->orderBy('id')->get(['id', 'name'])->toArray(),
        DB::table('role_permission')->orderBy('role_id')->orderBy('permission_id')->get()->map(fn ($r) => (array) $r)->all(),
        AuditLog::count(),
    ];
}

function actor(): User
{
    (new RolePermissionSeeder)->run();

    return User::factory()->create();
}

it('creates a role with trimmed name, permissions and an audit row [S3, S5]', function () {
    $actor = actor();

    $role = app(CreateRoleAction::class)->execute('  supervisor  ', ['sales.void', 'pos.sell'], $actor->id);

    $log = AuditLog::where('action', 'role.created')->sole();

    expect($role->name)->toBe('supervisor')
        ->and($role->is_system)->toBeFalse()
        ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['pos.sell', 'sales.void'])
        ->and($log->auditable_type)->toBe('role')
        ->and($log->auditable_id)->toBe($role->id)
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->before)->toBeNull()
        ->and($log->after)->toBe(['name' => 'supervisor', 'permissions' => ['pos.sell', 'sales.void']]);
});

it('rejects invalid names and unknown permissions without touching anything [S3]', function (string $name, array $perms) {
    $actor = actor();
    $before = snapshot();

    expect(fn () => app(CreateRoleAction::class)->execute($name, $perms, $actor->id))
        ->toThrow(InvalidArgumentException::class);

    expect(snapshot())->toBe($before);
})->with([
    'empty' => ['   ', []],
    'too long' => [str_repeat('a', 101), []],
    'duplicate' => ['cajero', []],
    'duplicate other case' => ['CAJERO', []],
    'unknown permission' => ['nuevo', ['nope.nope']],
]);

it('updates name and permissions and audits before and after [S3, S5]', function () {
    $actor = actor();
    $cajero = Role::where('name', 'cajero')->first();

    app(UpdateRoleAction::class)->execute($cajero, 'cajero senior', ['pos.sell', 'sales.return'], $actor->id);

    $log = AuditLog::where('action', 'role.updated')->sole();

    expect($cajero->fresh()->name)->toBe('cajero senior')
        ->and($cajero->permissions()->pluck('name')->sort()->values()->all())->toBe(['pos.sell', 'sales.return'])
        ->and($log->auditable_id)->toBe($cajero->id)
        ->and($log->before)->toBe(['name' => 'cajero', 'permissions' => ['pos.close-own', 'pos.sell']])
        ->and($log->after)->toBe(['name' => 'cajero senior', 'permissions' => ['pos.sell', 'sales.return']]);
});

it('does not audit an update that changes nothing [S5]', function () {
    $actor = actor();
    $cajero = Role::where('name', 'cajero')->first();

    app(UpdateRoleAction::class)->execute($cajero, 'cajero', ['pos.sell', 'pos.close-own'], $actor->id);

    expect(AuditLog::count())->toBe(0);
});

it('rejects a duplicate name on update but allows keeping its own [S3]', function () {
    $actor = actor();
    $cajero = Role::where('name', 'cajero')->first();
    $before = snapshot();

    expect(fn () => app(UpdateRoleAction::class)->execute($cajero, 'carnicero', ['pos.sell'], $actor->id))
        ->toThrow(InvalidArgumentException::class);
    expect(snapshot())->toBe($before);

    app(UpdateRoleAction::class)->execute($cajero, 'cajero', ['pos.sell'], $actor->id);
    expect($cajero->permissions()->count())->toBe(1);
});

it('lets the system role be renamed but never lose permissions [S3]', function () {
    $actor = actor();
    $admin = Role::where('is_system', true)->first();
    $all = collect(PermissionKey::cases())->map->value->all();

    app(UpdateRoleAction::class)->execute($admin, 'dueño', $all, $actor->id);
    expect($admin->fresh()->name)->toBe('dueño')->and($admin->fresh()->is_system)->toBeTrue();

    $before = snapshot();
    expect(fn () => app(UpdateRoleAction::class)->execute($admin, 'dueño', ['pos.sell'], $actor->id))
        ->toThrow(RuntimeException::class, 'El rol administrador conserva todos los permisos.');
    expect(snapshot())->toBe($before);
});

it('deletes a role without users and audits it [S3, S5]', function () {
    $actor = actor();
    $carnicero = Role::where('name', 'carnicero')->first();

    app(DeleteRoleAction::class)->execute($carnicero, $actor->id);

    $log = AuditLog::where('action', 'role.deleted')->sole();

    expect(Role::where('name', 'carnicero')->exists())->toBeFalse()
        ->and($log->auditable_id)->toBe($carnicero->id)
        ->and($log->before['name'])->toBe('carnicero')
        ->and($log->after)->toBeNull();
});

it('refuses to delete the system role or a role with users [S3]', function () {
    $actor = actor();
    $cajero = Role::where('name', 'cajero')->first();
    $cajero->users()->attach(User::factory()->create());
    $before = snapshot();

    expect(fn () => app(DeleteRoleAction::class)->execute(Role::where('is_system', true)->first(), $actor->id))
        ->toThrow(RuntimeException::class, 'El rol administrador no se puede borrar.');
    expect(fn () => app(DeleteRoleAction::class)->execute($cajero, $actor->id))
        ->toThrow(RuntimeException::class, 'No se puede borrar un rol que tiene usuarios asignados.');

    expect(snapshot())->toBe($before);
});

it('rejects removing roles.manage from the only role that grants it to users [S7]', function () {
    $actor = actor();
    $custom = app(CreateRoleAction::class)->execute('gestor', ['roles.manage', 'pos.sell'], $actor->id);
    $custom->users()->attach(User::factory()->create());
    $before = snapshot();

    expect(fn () => app(UpdateRoleAction::class)->execute($custom, 'gestor', ['pos.sell'], $actor->id))
        ->toThrow(RuntimeException::class, 'Debe quedar al menos un usuario activo con permiso para gestionar roles.');

    expect(snapshot())->toBe($before);
});

it('allows removing roles.manage while another user still holds it [S7]', function () {
    $actor = actor();
    $custom = app(CreateRoleAction::class)->execute('gestor', ['roles.manage', 'pos.sell'], $actor->id);
    $custom->users()->attach(User::factory()->create());
    Role::where('is_system', true)->first()->users()->attach(User::factory()->create());

    app(UpdateRoleAction::class)->execute($custom, 'gestor', ['pos.sell'], $actor->id);

    expect($custom->permissions()->pluck('name')->all())->toBe(['pos.sell']);
});
