<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// S2–S6: tema de la oficina (docs/design.md §2.5). La marca de settings debe llegar al panel (C6).

function themeAdmin(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'administrador')->first());

    return $user->fresh();
}

it('loads the compiled office theme in the login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('theme', false)
        ->assertSee('build/assets/theme-', false);
});

it('injects the configured brand colors in the panel head, escaped [S5]', function () {
    app(UpdateSettingsAction::class)->execute([
        CompanySettings::BRAND_COLOR => '#123456',
        CompanySettings::ACCENT_COLOR => '#ABCDEF',
    ], null);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('html:root { --brand: #123456; --accent: #ABCDEF; }', false);

    $this->actingAs(themeAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('build/assets/theme-', false)
        ->assertSee('html:root { --brand: #123456; --accent: #ABCDEF; }', false);
});

it('uses the neutral brand defaults in the panel without configuration [S5]', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('--brand: '.CompanySettings::DEFAULT_BRAND_COLOR.'; --accent: '.CompanySettings::DEFAULT_ACCENT_COLOR, false);
});

it('ships the theme file and the local fonts [S2, S4]', function () {
    expect(file_exists(resource_path('css/filament/admin/theme.css')))->toBeTrue();

    foreach (['baloo-2-latin-700', 'baloo-2-latin-800', 'figtree-latin-400', 'figtree-latin-600', 'figtree-latin-700'] as $font) {
        $path = public_path("fonts/{$font}.woff2");
        expect(file_exists($path))->toBeTrue()
            ->and(file_get_contents($path, false, null, 0, 4))->toBe('wOF2');
    }

    expect(file_exists(public_path('fonts/OFL-Baloo-2.txt')))->toBeTrue()
        ->and(file_exists(public_path('fonts/OFL-Figtree.txt')))->toBeTrue();
});

it('does not use CDN fonts and declares the local ones in app.css [S4]', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@font-face')
        ->and($css)->toContain('/fonts/figtree-latin-400.woff2')
        ->and($css)->toContain('/fonts/baloo-2-latin-800.woff2')
        ->and((string) file_get_contents(resource_path('views/app.blade.php')))->not->toContain('@fonts')
        ->and((string) file_get_contents(base_path('vite.config.js')))->not->toContain('bunny');
});

it('keeps hard-coded brand hex colors out of the theme [S5]', function () {
    $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

    expect(preg_match('/#[0-9a-fA-F]{3,8}\b/', $theme))->toBe(0);
});

// Menú lateral (d-dashboard.html): tarjeta «Tasa BCV de hoy», BR-02. Las horas se muestran en Caracas (C13).

it('shows today\'s BCV rate with its source and Caracas time in the sidebar [BR-02]', function () {
    Carbon::setTestNow('2026-11-09 14:00:00'); // 10:00 a. m. en Caracas
    (new RegisterExchangeRateAction)->execute(
        '233.5600', 'automatic', null, Carbon::parse('2026-11-09 12:02:00') // 8:02 a. m. en Caracas
    );

    $this->actingAs(themeAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Tasa BCV de hoy')
        ->assertSee('Bs 233,56')
        ->assertSee('Carga automática · 8:02 a. m.');
});

it('labels the rate with its date when it is not from today [BR-02]', function () {
    Carbon::setTestNow('2026-11-10 14:00:00');
    (new RegisterExchangeRateAction)->execute(
        '233.5600', 'manual', null, Carbon::parse('2026-11-09 20:30:00') // 4:30 p. m. del 9 en Caracas
    );

    $this->actingAs(themeAdmin())
        ->get('/admin')
        ->assertSee('Tasa BCV del 09/11')
        ->assertSee('Carga manual · 4:30 p. m.')
        ->assertDontSee('Tasa BCV de hoy');
});

it('tells when there is no rate registered yet [BR-02]', function () {
    $this->actingAs(themeAdmin())
        ->get('/admin')
        ->assertSee('Sin tasa registrada');
});

it('uses brand-night #2A1215 as the sidebar background by default [S3]', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toMatch('/--brand-night:\s*#2a1215;/i');
});
