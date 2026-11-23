<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Settings\Models\Permission;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

// M4 2.4 · DEC-05 (2 A), C14: permisos inventory.* solo del administrador, vía Policies.

function inventoryUserWithRole(string $roleName): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $roleName)->first());

    return $user;
}

function inventoryUserWithOnly(PermissionKey ...$keys): User
{
    $role = Role::create(['name' => 'rol-'.uniqid()]);
    $role->permissions()->sync(collect($keys)->map(fn ($k) => Permission::firstOrCreate(['name' => $k->value])->id)->all());

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

it('declares the inventory permissions with Spanish label and module [DEC-05]', function () {
    expect(PermissionKey::InventoryView->value)->toBe('inventory.view')
        ->and(PermissionKey::InventoryRegisterShrinkage->value)->toBe('inventory.register-shrinkage')
        ->and(PermissionKey::InventoryCount->value)->toBe('inventory.count');

    foreach ([PermissionKey::InventoryView, PermissionKey::InventoryRegisterShrinkage, PermissionKey::InventoryCount] as $key) {
        expect($key->module())->toBe('Inventario')
            ->and(trim($key->label()))->not->toBe('');
    }
});

it('gives the three inventory permissions to administrador only [DEC-05, BR-03]', function () {
    $admin = inventoryUserWithRole('administrador');

    expect($admin->hasPermission('inventory.view'))->toBeTrue()
        ->and($admin->hasPermission('inventory.register-shrinkage'))->toBeTrue()
        ->and($admin->hasPermission('inventory.count'))->toBeTrue();

    foreach (['cajero', 'carnicero'] as $roleName) {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->first());

        expect($user->hasPermission('inventory.view'))->toBeFalse($roleName)
            ->and($user->hasPermission('inventory.register-shrinkage'))->toBeFalse($roleName)
            ->and($user->hasPermission('inventory.count'))->toBeFalse($roleName);
    }
});

it('lets BatchPolicy follow inventory.view and inventory.register-shrinkage [C14]', function () {
    $batch = Batch::factory()->create();
    $viewer = inventoryUserWithOnly(PermissionKey::InventoryView);
    $registrar = inventoryUserWithOnly(PermissionKey::InventoryRegisterShrinkage);
    $nobody = User::factory()->create();

    expect(Gate::forUser($viewer)->allows('viewAny', Batch::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('view', $batch))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('registerShrinkage', $batch))->toBeFalse()
        ->and(Gate::forUser($registrar)->allows('registerShrinkage', $batch))->toBeTrue()
        ->and(Gate::forUser($registrar)->allows('viewAny', Batch::class))->toBeFalse()
        ->and(Gate::forUser($nobody)->allows('viewAny', Batch::class))->toBeFalse()
        ->and(Gate::forUser($nobody)->allows('view', $batch))->toBeFalse()
        ->and(Gate::forUser($nobody)->allows('registerShrinkage', $batch))->toBeFalse();
});

it('lets InventoryCountPolicy follow inventory.count for every action [C14, BR-16]', function () {
    $count = InventoryCount::factory()->create();
    $counter = inventoryUserWithOnly(PermissionKey::InventoryCount);
    $viewerOnly = inventoryUserWithOnly(PermissionKey::InventoryView, PermissionKey::InventoryRegisterShrinkage);

    foreach (['viewAny' => InventoryCount::class, 'create' => InventoryCount::class, 'view' => $count,
        'registerWeight' => $count, 'confirm' => $count, 'cancel' => $count] as $ability => $target) {
        expect(Gate::forUser($counter)->allows($ability, $target))->toBeTrue($ability)
            ->and(Gate::forUser($viewerOnly)->allows($ability, $target))->toBeFalse($ability);
    }
});
