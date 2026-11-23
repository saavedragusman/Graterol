<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\ExchangeRate;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\ExchangeRateResource;
use App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// S4/S5/S6 (C14): pantalla «Configuración › Tasa BCV».

function rateScreenUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('forbids a cajero from the rate screen [C14]', function () {
    rateScreenUser('cajero');

    $this->get(ExchangeRateResource::getUrl('index'))->assertForbidden();
});

it('lists the history newest first with Spanish labels [S4]', function () {
    $admin = rateScreenUser('administrador');
    $old = (new RegisterExchangeRateAction)->execute('80.0000', 'automatic', effectiveAt: now()->subDay());
    $new = (new RegisterExchangeRateAction)->execute('1234.5000', 'manual', $admin->id);

    Livewire::test(ListExchangeRates::class)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('Automática')
        ->assertSee('Manual')
        ->assertSee('Sistema')
        ->assertSee($admin->name)
        ->assertSee('1.234,50')->assertDontSee('1.234,5000')
        ->assertSee('80,00')->assertDontSee('80,0000');
});

it('filters by source [S4]', function () {
    rateScreenUser('administrador');
    $auto = (new RegisterExchangeRateAction)->execute('80', 'automatic');
    $manual = (new RegisterExchangeRateAction)->execute('81', 'manual');

    Livewire::test(ListExchangeRates::class)
        ->filterTable('source', 'manual')
        ->assertCanSeeTableRecords([$manual])
        ->assertCanNotSeeTableRecords([$auto]);
});

it('registers a rate from the modal and audits it [S4][S5]', function () {
    $admin = rateScreenUser('administrador');

    Livewire::test(ListExchangeRates::class)
        ->callAction('register', ['rate' => '918,7662'])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $rate = ExchangeRate::firstOrFail();
    expect($rate->rate)->toBe('918.77')
        ->and($rate->source)->toBe('manual')
        ->and($rate->registered_by)->toBe($admin->id)
        ->and(AuditLog::where('action', 'exchange_rate.registered')->count())->toBe(1);
});

it('shows a danger notification and stores nothing for an invalid rate [S4]', function () {
    rateScreenUser('administrador');

    Livewire::test(ListExchangeRates::class)
        ->callAction('register', ['rate' => '1.23456'])
        ->assertNotified('No se pudo registrar la tasa');

    expect(ExchangeRate::count())->toBe(0)->and(AuditLog::count())->toBe(0);
});

it('has no edit, view or delete pages or actions [S6]', function () {
    $admin = rateScreenUser('administrador');
    $row = (new RegisterExchangeRateAction)->execute('80', 'manual', $admin->id);

    expect(array_keys(ExchangeRateResource::getPages()))->toBe(['index']);

    Livewire::test(ListExchangeRates::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('audit log screen labels the new type and action [S5]', function () {
    expect(AuditLogResource::typeLabel('exchange_rate'))->toBe('Tasa BCV')
        ->and(AuditLogResource::actionLabel('exchange_rate.registered'))->toBe('Registro manual');
});
