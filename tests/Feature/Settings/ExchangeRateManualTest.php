<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterManualExchangeRateAction;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\ExchangeRate;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// S4/S5/S6 (C9, C14, C15): registro manual de la tasa BCV con auditoría.

function rateActor(string $role = 'administrador'): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());

    return $user->fresh();
}

it('registers a manual rate with source, actor and audit log [S4][S5]', function () {
    $actor = rateActor();

    $rate = app(RegisterManualExchangeRateAction::class)->execute('918.7662', $actor); // se guarda redondeada a 2 decimales

    expect($rate->source)->toBe('manual')
        ->and($rate->rate)->toBe('918.77')
        ->and($rate->registered_by)->toBe($actor->id)
        ->and(ExchangeRate::count())->toBe(1);

    $log = AuditLog::firstOrFail();
    expect($log->auditable_type)->toBe('exchange_rate')
        ->and($log->auditable_id)->toBe($rate->id)
        ->and($log->action)->toBe('exchange_rate.registered')
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->before)->toBeNull()
        ->and($log->after['rate'])->toBe('918.77')
        ->and($log->after['source'])->toBe('manual')
        ->and($log->after)->toHaveKey('effective_at');
});

it('accepts comma input and normalizes to 2 decimals [S4][C9]', function (string $input, string $expected) {
    $rate = app(RegisterManualExchangeRateAction::class)->execute($input, rateActor());

    expect($rate->rate)->toBe($expected);
})->with([
    ['918,7662', '918.77'],
    ['918.764', '918.76'],
    ['918.765', '918.77'], // half-up
    ['85,5', '85.50'],
    [' 90 ', '90.00'],
    ['0.01', '0.01'],
    ['999999999', '999999999.00'],
]);

it('stores the previous current rate as the audit before [S5]', function () {
    $actor = rateActor();
    $previous = app(RegisterManualExchangeRateAction::class)->execute('80', $actor);
    app(RegisterManualExchangeRateAction::class)->execute('85,5', $actor);

    $log = AuditLog::orderByDesc('id')->firstOrFail();

    expect($log->before['rate'])->toBe('80.00')
        ->and($log->before['source'])->toBe('manual')
        ->and($log->before['effective_at'])->toBe($previous->effective_at->toIso8601String())
        ->and($log->after['rate'])->toBe('85.50');
});

it('rejects invalid rates leaving rows and audit unchanged [S4][C9]', function (string $input) {
    $actor = rateActor();

    expect(fn () => app(RegisterManualExchangeRateAction::class)->execute($input, $actor))
        ->toThrow(InvalidArgumentException::class);

    expect(ExchangeRate::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
})->with(['0', '0,0000', '0.004', '-5', 'abc', '', '1.23456', '1000000000', '1e3', '1.234,5', '12,']);

it('never updates previous rows when registering a new one [S6]', function () {
    $actor = rateActor();
    $first = app(RegisterManualExchangeRateAction::class)->execute('80', $actor);
    app(RegisterManualExchangeRateAction::class)->execute('85', $actor);

    expect(ExchangeRate::count())->toBe(2)
        ->and($first->fresh()->rate)->toBe('80.00');
});

it('grants rates.manage only to the administrator [S5]', function () {
    $admin = rateActor('administrador');
    $cajero = rateActor('cajero');

    expect(PermissionKey::RatesManage->value)->toBe('rates.manage')
        ->and(PermissionKey::RatesManage->label())->toBe('Registrar tasa BCV')
        ->and(PermissionKey::RatesManage->module())->toBe('Configuración')
        ->and($admin->hasPermission('rates.manage'))->toBeTrue()
        ->and($cajero->hasPermission('rates.manage'))->toBeFalse();
});

it('denies writes on exchange rates to everyone via the policy [S6][C14]', function () {
    $admin = rateActor();
    $row = app(RegisterManualExchangeRateAction::class)->execute('80', $admin);

    expect($admin->can('viewAny', ExchangeRate::class))->toBeTrue()
        ->and($admin->can('view', $row))->toBeTrue()
        ->and($admin->can('create', ExchangeRate::class))->toBeTrue()
        ->and($admin->can('update', $row))->toBeFalse()
        ->and($admin->can('delete', $row))->toBeFalse()
        ->and($admin->can('restore', $row))->toBeFalse()
        ->and($admin->can('forceDelete', $row))->toBeFalse();

    $cajero = rateActor('cajero');
    expect($cajero->can('viewAny', ExchangeRate::class))->toBeFalse()
        ->and($cajero->can('create', ExchangeRate::class))->toBeFalse();
});
