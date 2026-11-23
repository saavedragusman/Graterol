<?php

declare(strict_types=1);

use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// S4: un usuario inactivo no entra al panel ni a la PWA, aunque tenga la sesión abierta.

function inactiveWithRole(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->inactive()->create();
    $user->roles()->attach(Role::where('name', 'administrador')->first());

    return $user;
}

it('denies an inactive user on the panel [S4]', function () {
    $user = inactiveWithRole();

    // Las rutas de Filament no pasan por el grupo web: las frena canAccessPanel.
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('denies an inactive user on a PWA route [S4]', function () {
    $user = inactiveWithRole();

    $this->actingAs($user)->get(route('butchery.orders.index'))->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

it('lets an active user through the PWA [S4]', function () {
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'carnicero')->first());

    $this->actingAs($user)->get(route('butchery.orders.index'))->assertOk();
});

it('denies the panel to an inactive user through canAccessPanel [S4]', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect(User::factory()->inactive()->make()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->make()->canAccessPanel($panel))->toBeTrue();
});
