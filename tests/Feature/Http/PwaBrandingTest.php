<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

// C6: la PWA toma nombre y colores de settings (docs/design.md §2.4).

function pwaUser(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'carnicero')->first());

    return $user;
}

it('injects the configured brand colors and shares the company [C6]', function () {
    app(UpdateSettingsAction::class)->execute([
        CompanySettings::NAME => 'Carnes Demo',
        CompanySettings::BRAND_COLOR => '#123456',
        CompanySettings::ACCENT_COLOR => '#ABCDEF',
        CompanySettings::LOGO_PATH => 'branding/logo.png',
    ], null);

    $this->actingAs(pwaUser())
        ->get(route('butchery.orders.index'))
        ->assertOk()
        ->assertSee('--brand: #123456', false)
        ->assertSee('--accent: #ABCDEF', false)
        ->assertSee('<title inertia>Carnes Demo</title>', false)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('company.name', 'Carnes Demo')
            ->where('company.logoUrl', fn (string $url) => str_ends_with($url, '/storage/branding/logo.png')));
});

it('uses the neutral product defaults without configuration [C6]', function () {
    $this->actingAs(pwaUser())
        ->get(route('butchery.orders.index'))
        ->assertOk()
        ->assertSee('--brand: '.CompanySettings::DEFAULT_BRAND_COLOR, false)
        ->assertSee('<title inertia>'.CompanySettings::DEFAULT_NAME.'</title>', false)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('company.name', CompanySettings::DEFAULT_NAME)
            ->where('company.logoUrl', null));
});
