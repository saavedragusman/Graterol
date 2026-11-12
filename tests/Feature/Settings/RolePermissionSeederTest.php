<?php

declare(strict_types=1);

use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
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
        ->and($user->hasPermission('butchery.process'))->toBeTrue();
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
        ->and($user->hasPermission('butchery.process'))->toBeTrue()
        ->and($user->hasPermission('pos.sell'))->toBeFalse()
        ->and($user->hasPermission('sales.void'))->toBeFalse();
});

it('is idempotent and does not duplicate permissions or roles [BR-03]', function () {
    (new RolePermissionSeeder)->run();
    (new RolePermissionSeeder)->run();

    expect(Permission::count())->toBe(6)
        ->and(Role::count())->toBe(3);
});
