<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// S3, S5, S6, S7: pantalla «Configuración › Roles», autorizada solo por RolePolicy (roles.manage).

function roleUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('forbids a cajero from the roles screen [S6]', function () {
    roleUser('cajero');

    Livewire::test(ListRoles::class)->assertForbidden();
    Livewire::test(CreateRole::class)->assertForbidden();
    expect(RoleResource::canViewAny())->toBeFalse();
});

it('lists roles with system badge, users and permissions count [S3]', function () {
    roleUser('administrador');
    $cajero = Role::where('name', 'cajero')->first();

    Livewire::test(ListRoles::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Role::all())
        ->assertSee('Sistema')
        ->assertTableColumnStateSet('permissions_count', 2, $cajero)
        ->assertTableColumnStateSet('users_count', 0, $cajero);
});

it('creates a role from the form through the Action [S3, S5]', function () {
    $admin = roleUser('administrador');

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'supervisor',
            'permission_groups' => ['pos' => ['pos.sell'], 'ventas' => ['sales.void']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::where('name', 'supervisor')->sole();

    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['pos.sell', 'sales.void'])
        ->and(AuditLog::where('action', 'role.created')->sole()->user_id)->toBe($admin->id);
});

it('shows an error and creates nothing on a duplicate name [S3]', function () {
    roleUser('administrador');
    $count = Role::count();

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'cajero'])
        ->call('create');

    expect(Role::count())->toBe($count)->and(AuditLog::count())->toBe(0);
});

it('edits a role and audits it [S3, S5]', function () {
    roleUser('administrador');
    $cajero = Role::where('name', 'cajero')->first();

    Livewire::test(EditRole::class, ['record' => $cajero->getKey()])
        ->assertFormSet(['name' => 'cajero', 'permission_groups.pos' => ['pos.sell', 'pos.close-own']])
        ->fillForm(['name' => 'cajero 2', 'permission_groups' => ['pos' => ['pos.sell'], 'ventas' => ['sales.return']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cajero->fresh()->name)->toBe('cajero 2')
        ->and($cajero->permissions()->pluck('name')->sort()->values()->all())->toBe(['pos.sell', 'sales.return'])
        ->and(AuditLog::where('action', 'role.updated')->count())->toBe(1);
});

it('shows every permission checked and disabled for the system role [S3]', function () {
    roleUser('administrador');
    $admin = Role::where('is_system', true)->first();

    $component = Livewire::test(EditRole::class, ['record' => $admin->getKey()])
        ->assertFormFieldDisabled('permission_groups.pos')
        ->assertFormSet(['permission_groups.pos' => ['pos.sell', 'pos.close-own']]);

    $component->fillForm(['name' => 'dueño'])->call('save')->assertHasNoFormErrors();

    expect($admin->fresh()->name)->toBe('dueño')
        ->and($admin->permissions()->count())->toBe(count(PermissionKey::cases()));
});

it('keeps the edit form open with an error when the lockout guard rejects [S7]', function () {
    roleUser('administrador');
    $custom = Role::create(['name' => 'gestor']);
    $custom->permissions()->sync(Permission::whereIn('name', ['roles.manage'])->pluck('id'));
    $gestor = User::factory()->create();
    $custom->users()->attach($gestor);
    // Gestor queda como único usuario con roles.manage, y es quien edita su propio rol.
    Role::where('is_system', true)->first()->users()->detach();
    test()->actingAs($gestor->fresh());

    Livewire::test(EditRole::class, ['record' => $custom->getKey()])
        ->fillForm(['name' => 'gestor', 'permission_groups' => ['pos' => ['pos.sell'], 'configuracion' => []]])
        ->call('save');

    expect($custom->permissions()->pluck('name')->all())->toBe(['roles.manage']);
});

it('deletes a role through the Action and refuses the system role [S3, S5]', function () {
    roleUser('administrador');
    $carnicero = Role::where('name', 'carnicero')->first();
    $admin = Role::where('is_system', true)->first();

    Livewire::test(EditRole::class, ['record' => $carnicero->getKey()])
        ->callAction(DeleteAction::class);

    expect(Role::where('name', 'carnicero')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'role.deleted')->count())->toBe(1);

    Livewire::test(EditRole::class, ['record' => $admin->getKey()])
        ->assertActionHidden(DeleteAction::class);
});

it('does not delete a role that has users and notifies [S3]', function () {
    roleUser('administrador');
    $cajero = Role::where('name', 'cajero')->first();
    $cajero->users()->attach(User::factory()->create());

    Livewire::test(EditRole::class, ['record' => $cajero->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified('No se pudo borrar el rol');

    expect(Role::where('name', 'cajero')->exists())->toBeTrue();
});
