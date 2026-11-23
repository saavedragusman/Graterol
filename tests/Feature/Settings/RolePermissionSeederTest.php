<?php

declare(strict_types=1);

use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-03: roles iniciales como conjuntos de permisos module.action.

it('creates administrador with every permission [BR-03]', function () {
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'administrador')->first());

    expect($user->hasPermission('pos.sell'))->toBeTrue()
        ->and($user->hasPermission('sales.void'))->toBeTrue()
        ->and($user->hasPermission('sales.return'))->toBeTrue()
        ->and($user->hasPermission('purchasing.receive'))->toBeTrue()
        ->and($user->hasPermission('purchasing.view'))->toBeTrue()
        ->and($user->hasPermission('purchasing.create'))->toBeTrue()
        ->and($user->hasPermission('purchasing.void'))->toBeTrue()
        ->and($user->hasPermission('purchasing.manage-suppliers'))->toBeTrue()
        ->and($user->hasPermission('butchery.process'))->toBeTrue()
        ->and($user->hasPermission('butchery.manage-templates'))->toBeTrue()
        ->and($user->hasPermission('butchery.void'))->toBeTrue()
        ->and($user->hasPermission('catalog.manage'))->toBeTrue();
});

it('restricts cajero to pos and its own close, never void or receiving [BR-03]', function () {
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'cajero')->first());

    expect($user->hasPermission('pos.sell'))->toBeTrue()
        ->and($user->hasPermission('pos.close-own'))->toBeTrue()
        ->and($user->hasPermission('sales.void'))->toBeFalse()
        ->and($user->hasPermission('sales.return'))->toBeFalse()
        ->and($user->hasPermission('purchasing.receive'))->toBeFalse();
});

it('restricts carnicero to receiving and butchery, never pos or void [BR-03]', function () {
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'carnicero')->first());

    expect($user->hasPermission('purchasing.receive'))->toBeTrue()
        ->and($user->hasPermission('purchasing.view'))->toBeTrue()
        ->and($user->hasPermission('purchasing.create'))->toBeFalse()
        ->and($user->hasPermission('purchasing.void'))->toBeFalse()
        ->and($user->hasPermission('butchery.process'))->toBeTrue()
        ->and($user->hasPermission('butchery.manage-templates'))->toBeFalse()
        ->and($user->hasPermission('butchery.void'))->toBeFalse()
        ->and($user->hasPermission('pos.sell'))->toBeFalse()
        ->and($user->hasPermission('sales.void'))->toBeFalse();
});

it('is idempotent and does not duplicate permissions or roles [BR-03]', function () {
    (new RolePermissionSeeder)->run();
    (new RolePermissionSeeder)->run();

    expect(Permission::count())->toBe(count(PermissionKey::cases()))
        ->and(Role::count())->toBe(3);
});

it('marks administrador as system role with every permission [S1]', function () {
    (new RolePermissionSeeder)->run();

    $admin = Role::where('name', 'administrador')->first();

    expect($admin->is_system)->toBeTrue()
        ->and($admin->permissions()->count())->toBe(count(PermissionKey::cases()))
        ->and(Role::where('is_system', true)->count())->toBe(1)
        ->and(Role::where('name', 'cajero')->first()->is_system)->toBeFalse();
});

it('keeps edits made to cajero permissions on a second run [S1]', function () {
    (new RolePermissionSeeder)->run();

    $cajero = Role::where('name', 'cajero')->first();
    $cajero->permissions()->sync([Permission::where('name', 'catalog.manage')->first()->id]);

    (new RolePermissionSeeder)->run();

    expect($cajero->fresh()->permissions()->pluck('name')->all())->toBe(['catalog.manage']);
});

it('does not recreate a renamed or deleted initial role nor re-attach its permissions [S1]', function () {
    (new RolePermissionSeeder)->run();

    Role::where('name', 'administrador')->first()->update(['name' => 'dueño']);
    Role::where('name', 'carnicero')->first()->delete();

    (new RolePermissionSeeder)->run();

    expect(Role::where('is_system', true)->count())->toBe(1)
        ->and(Role::where('name', 'dueño')->exists())->toBeTrue()
        ->and(Role::where('name', 'administrador')->exists())->toBeFalse();
});

it('gives administrador any newly added permission and never detaches [S1]', function () {
    (new RolePermissionSeeder)->run();

    $admin = Role::where('is_system', true)->first();
    $admin->permissions()->detach(Permission::where('name', 'sales.void')->first()->id);
    $extra = Permission::create(['name' => 'future.thing']);

    (new RolePermissionSeeder)->run();

    $names = $admin->fresh()->permissions()->pluck('name')->all();

    expect($names)->toContain('sales.void')
        ->and(Permission::where('name', 'future.thing')->exists())->toBeTrue()
        ->and($names)->toContain('future.thing')
        ->and($extra->exists)->toBeTrue();
});
