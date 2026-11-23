<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// C6: el panel de oficina toma nombre, logo y favicon de settings, sin recompilar.

function brandingAdmin(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'administrador')->first());

    return $user->fresh();
}

it('shows the configured company name, logo and favicon in the panel [C6]', function () {
    app(UpdateSettingsAction::class)->execute([
        CompanySettings::NAME => 'Carnes Demo',
        CompanySettings::LOGO_PATH => 'branding/logo.png',
        CompanySettings::FAVICON_PATH => 'branding/favicon.png',
    ], null);

    $this->actingAs(brandingAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('/storage/branding/logo.png', false)
        ->assertSee('/storage/branding/favicon.png', false)
        ->assertSee('Carnes Demo');
});

it('falls back to the neutral product name without configuration [C6]', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee(CompanySettings::DEFAULT_NAME);
});
