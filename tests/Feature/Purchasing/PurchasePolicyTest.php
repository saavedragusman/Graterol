<?php

declare(strict_types=1);

use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-03/C14: autorización solo por permisos module.action.

function userWithRole(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());

    return $user->fresh();
}

function aPurchase(): Purchase
{
    $supplier = Supplier::factory()->create();

    return Purchase::create([
        'supplier_id' => $supplier->id,
        'supplier_invoice_number' => 'F-001',
        'supplier_invoice_date' => now(),
        'exchange_rate' => '80.0000',
    ]);
}

it('lets administrador create, receive and void a purchase [BR-03]', function () {
    $admin = userWithRole('administrador');
    $purchase = aPurchase();

    expect($admin->can('create', Purchase::class))->toBeTrue()
        ->and($admin->can('receive', $purchase))->toBeTrue()
        ->and($admin->can('void', $purchase))->toBeTrue();
});

it('lets carnicero receive but never create or void a purchase [BR-03]', function () {
    $carnicero = userWithRole('carnicero');
    $purchase = aPurchase();

    expect($carnicero->can('receive', $purchase))->toBeTrue()
        ->and($carnicero->can('create', Purchase::class))->toBeFalse()
        ->and($carnicero->can('void', $purchase))->toBeFalse();
});

it('never lets cajero touch purchasing [BR-03]', function () {
    $cajero = userWithRole('cajero');
    $purchase = aPurchase();

    expect($cajero->can('viewAny', Purchase::class))->toBeFalse()
        ->and($cajero->can('create', Purchase::class))->toBeFalse()
        ->and($cajero->can('receive', $purchase))->toBeFalse();
});

it('only administrador manages suppliers [BR-03]', function () {
    $admin = userWithRole('administrador');
    $carnicero = userWithRole('carnicero');
    $supplier = Supplier::factory()->create();

    expect($admin->can('create', Supplier::class))->toBeTrue()
        ->and($admin->can('update', $supplier))->toBeTrue()
        ->and($carnicero->can('create', Supplier::class))->toBeFalse();
});
