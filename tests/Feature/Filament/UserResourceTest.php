<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// S2, S4, S5, S6, S7: pantalla «Configuración › Usuarios», autorizada solo por UserPolicy (users.manage).

function userScreenActor(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('forbids a cajero from the users screen [S6]', function () {
    userScreenActor('cajero');

    Livewire::test(ListUsers::class)->assertForbidden();
    Livewire::test(CreateUser::class)->assertForbidden();
    expect(UserResource::canViewAny())->toBeFalse();
});

it('lists users with roles and active state, and filters by it [S2, S4]', function () {
    userScreenActor('administrador');
    $active = User::factory()->create();
    $active->roles()->attach(Role::where('name', 'cajero')->first());
    $off = User::factory()->inactive()->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$active, $off])
        ->assertSee('Inactivo')
        ->assertSee('cajero')
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$off])
        ->assertCanNotSeeTableRecords([$active]);
});

it('creates a user from the form through the Action [S2, S5]', function () {
    $admin = userScreenActor('administrador');
    $cajero = Role::where('name', 'cajero')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ana', 'email' => 'ana@example.test',
            'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'role_ids' => [$cajero->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'ana@example.test')->sole();

    expect($user->is_active)->toBeTrue()
        ->and(Hash::check('secreto123', $user->password))->toBeTrue()
        ->and($user->roles()->pluck('name')->all())->toBe(['cajero'])
        ->and(AuditLog::where('action', 'user.created')->sole()->user_id)->toBe($admin->id);
});

it('requires password confirmation and rejects a duplicate email [S2]', function () {
    userScreenActor('administrador');
    $count = User::count();

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Ana', 'email' => 'a@example.test', 'password' => 'secreto123', 'password_confirmation' => 'otra-cosa'])
        ->call('create')
        ->assertHasFormErrors(['password']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Ana', 'email' => User::first()->email, 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])
        ->call('create');

    expect(User::count())->toBe($count)->and(AuditLog::count())->toBe(0);
});

it('edits name, email and roles through the Action [S2, S5]', function () {
    userScreenActor('administrador');
    $user = User::factory()->create(['name' => 'Ana']);
    $cajero = Role::where('name', 'cajero')->first();
    $carnicero = Role::where('name', 'carnicero')->first();
    $user->roles()->attach($cajero);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertFormSet(['name' => 'Ana', 'role_ids' => [$cajero->id]])
        ->fillForm(['name' => 'Ana María', 'role_ids' => [$carnicero->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Ana María')
        ->and($user->roles()->pluck('name')->all())->toBe(['carnicero'])
        ->and(AuditLog::where('action', 'user.roles_updated')->count())->toBe(1);
});

it('keeps the edit with an error when the lockout guard rejects [S7]', function () {
    $admin = userScreenActor('administrador');
    $manager = Role::where('is_system', true)->first();

    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->fillForm(['role_ids' => []])
        ->call('save');

    expect($admin->roles()->pluck('id')->all())->toBe([$manager->id])
        ->and(AuditLog::count())->toBe(0);
});

it('deactivates and reactivates from the table with confirmation [S4, S5]', function () {
    userScreenActor('administrador');
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('reactivate')->table($user))
        ->callAction(TestAction::make('deactivate')->table($user));

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(AuditLog::where('action', 'user.deactivated')->count())->toBe(1);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('reactivate')->table($user));

    expect($user->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::where('action', 'user.reactivated')->count())->toBe(1);
});

it('hides deactivate and delete for oneself [S4, S6]', function () {
    $admin = userScreenActor('administrador');

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($admin))
        ->assertActionHidden(TestAction::make('delete')->table($admin));
});

it('resets the password from a modal with confirmation [S2, S5]', function () {
    $admin = userScreenActor('administrador');
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($user), ['password' => 'corta', 'password_confirmation' => 'corta'])
        ->assertHasActionErrors();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($user), ['password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
        ->assertHasNoActionErrors();

    $log = AuditLog::where('action', 'user.password_reset')->sole();

    expect(Hash::check('nueva-clave-1', $user->fresh()->password))->toBeTrue()
        ->and($log->user_id)->toBe($admin->id);
});

it('deletes only users without history [S4]', function () {
    userScreenActor('administrador');
    $clean = User::factory()->create();
    $used = User::factory()->create();
    AuditLog::create(['auditable_type' => 'setting', 'auditable_id' => 1, 'action' => 'updated', 'user_id' => $used->id]);

    Livewire::test(ListUsers::class)
        ->assertActionEnabled(TestAction::make('delete')->table($clean))
        ->assertActionDisabled(TestAction::make('delete')->table($used))
        ->callAction(TestAction::make('delete')->table($clean));

    expect(User::find($clean->id))->toBeNull()
        ->and(User::find($used->id))->not->toBeNull()
        ->and(AuditLog::where('action', 'user.deleted')->count())->toBe(1);
});
