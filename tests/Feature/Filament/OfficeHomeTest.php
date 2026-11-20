<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use App\Support\HomeGreeting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// Inicio de la oficina (d-dashboard.html): saludo y fecha en Caracas (C13), sin tarjetas de Filament.

function homeAdmin(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'administrador')->first());

    return $user->fresh();
}

it('greets by the Caracas hour', function (string $utc, string $greeting) {
    expect(HomeGreeting::greeting(Carbon::parse($utc, 'UTC')))->toBe($greeting);
})->with([
    'morning' => ['2026-11-09 12:00:00', 'Buenos días'],   // 8:00 a. m.
    'afternoon' => ['2026-11-09 18:00:00', 'Buenas tardes'], // 2:00 p. m.
    'night' => ['2026-11-10 01:30:00', 'Buenas noches'],   // 9:30 p. m. del 9
]);

it('writes the date in Spanish in Caracas time', function () {
    expect(HomeGreeting::dateLine(Carbon::parse('2026-11-10 02:00:00', 'UTC')))
        ->toBe('Lunes 9 de noviembre de 2026'); // 10:00 p. m. del lunes 9 en Caracas (UTC-4)
});

it('shows a clean Inicio with greeting and date, without the Filament cards', function () {
    Carbon::setTestNow('2026-11-09 12:00:00');

    $this->actingAs(homeAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Inicio')
        ->assertSee('Buenos días')
        ->assertSee('Lunes 9 de noviembre de 2026')
        ->assertDontSee('Bienvenida/o')
        ->assertDontSee('Documentación')
        ->assertDontSee('Escritorio');
});

it('labels the navigation entries in Spanish sentence case', function () {
    $this->actingAs(homeAdmin())
        ->get('/admin')
        ->assertSee('Órdenes de despiece')
        ->assertSee('Plantillas de despiece')
        ->assertDontSee('Órdenes De Despiece')
        ->assertDontSee('Plantillas De Despiece');
});

it('shows the configured subtitle next to the logo and hides it when empty [C6]', function () {
    $this->actingAs(homeAdmin())->get('/admin')->assertDontSee('erp-brand-subtitle', false);

    app(UpdateSettingsAction::class)->execute([CompanySettings::SUBTITLE => 'Sede Demo'], null);

    $this->actingAs(homeAdmin())->get('/admin')->assertSee('Sede Demo');
});

it('stores, trims and clears the subtitle with audit [C6][C15]', function () {
    $action = app(UpdateSettingsAction::class);
    $action->execute([CompanySettings::SUBTITLE => '  Sede Demo  '], null);
    expect(app(CompanySettings::class)->subtitle())->toBe('Sede Demo');

    $action->execute([CompanySettings::SUBTITLE => ''], null);
    expect(app(CompanySettings::class)->subtitle())->toBeNull();

    expect(fn () => $action->execute([CompanySettings::SUBTITLE => str_repeat('a', 101)], null))
        ->toThrow(InvalidArgumentException::class);
});
