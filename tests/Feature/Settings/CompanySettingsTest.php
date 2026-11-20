<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// C6: los datos de la empresa son configuración en datos, nunca código.
// C15: cada cambio de configuración queda auditado.

it('uses neutral product defaults when nothing is configured [C6]', function () {
    $company = app(CompanySettings::class);

    expect($company->name())->toBe(CompanySettings::DEFAULT_NAME)
        ->and($company->brandColor())->toBe(CompanySettings::DEFAULT_BRAND_COLOR)
        ->and($company->accentColor())->toBe(CompanySettings::DEFAULT_ACCENT_COLOR)
        ->and($company->logoUrl())->toBeNull()
        ->and($company->faviconUrl())->toBeNull()
        ->and($company->charcuteriaVatRate())->toBe('16.00');
});

it('stores new values and audits before and after [C6][C15]', function () {
    $user = User::factory()->create();

    app(UpdateSettingsAction::class)->execute([
        CompanySettings::NAME => 'Carnes Demo',
        CompanySettings::BRAND_COLOR => '#123456',
    ], $user->id);

    $company = app(CompanySettings::class);
    expect($company->name())->toBe('Carnes Demo')
        ->and($company->brandColor())->toBe('#123456');

    $audit = AuditLog::where('auditable_type', 'setting')->orderBy('id')->get();
    expect($audit)->toHaveCount(2)
        ->and($audit[0]->user_id)->toBe($user->id)
        ->and($audit[0]->action)->toBe('updated')
        ->and($audit[0]->before)->toBe(['key' => CompanySettings::NAME, 'value' => null])
        ->and($audit[0]->after)->toBe(['key' => CompanySettings::NAME, 'value' => 'Carnes Demo']);
});

it('does not audit values that did not change [C15]', function () {
    $action = app(UpdateSettingsAction::class);
    $action->execute([CompanySettings::NAME => 'Carnes Demo'], null);
    $action->execute([CompanySettings::NAME => 'Carnes Demo'], null);

    expect(AuditLog::where('auditable_type', 'setting')->count())->toBe(1);
});

it('rejects invalid input and leaves stored data unchanged [C6]', function (array $values) {
    $action = app(UpdateSettingsAction::class);
    $action->execute([CompanySettings::NAME => 'Carnes Demo'], null);

    expect(fn () => $action->execute($values, null))->toThrow(InvalidArgumentException::class);

    expect(Setting::pluck('value', 'key')->all())->toBe([CompanySettings::NAME => 'Carnes Demo'])
        ->and(AuditLog::where('auditable_type', 'setting')->count())->toBe(1);
})->with([
    'malformed hex' => [[CompanySettings::NAME => 'Otro', CompanySettings::BRAND_COLOR => 'rojo']],
    'short hex' => [[CompanySettings::ACCENT_COLOR => '#FFF']],
    'empty name' => [[CompanySettings::NAME => '  ']],
    'vat over 100' => [[CompanySettings::CHARCUTERIA_VAT_RATE => '100.01']],
    'vat not numeric' => [[CompanySettings::CHARCUTERIA_VAT_RATE => 'diez']],
    'unknown key' => [['company.slogan' => 'x']],
]);

it('normalizes the VAT rate to two decimals [BR-05]', function () {
    app(UpdateSettingsAction::class)->execute([CompanySettings::CHARCUTERIA_VAT_RATE => '8'], null);

    expect(app(CompanySettings::class)->charcuteriaVatRate())->toBe('8.00');
});

it('clears logo and favicon when set to null [C6]', function () {
    $action = app(UpdateSettingsAction::class);
    $action->execute([CompanySettings::LOGO_PATH => 'branding/logo.png'], null);
    expect(app(CompanySettings::class)->logoUrl())->toEndWith('/storage/branding/logo.png');

    $action->execute([CompanySettings::LOGO_PATH => null], null);
    expect(app(CompanySettings::class)->logoUrl())->toBeNull();
});

it('rejects a hex color with a trailing newline [C6]', function () {
    expect(fn () => app(UpdateSettingsAction::class)->execute(
        [CompanySettings::BRAND_COLOR => "#AABBCC\n"], null
    ))->toThrow(InvalidArgumentException::class);
});

it('falls back to the default when a stored color is malformed [C6]', function () {
    // Filas escritas fuera de UpdateSettingsAction no llegan crudas al CSS del layout.
    Setting::create(['key' => CompanySettings::BRAND_COLOR, 'value' => 'x; } body{display:none']);
    Setting::create(['key' => CompanySettings::ACCENT_COLOR, 'value' => "#AABBCC\n"]);

    $company = app(CompanySettings::class);
    expect($company->brandColor())->toBe(CompanySettings::DEFAULT_BRAND_COLOR)
        ->and($company->accentColor())->toBe(CompanySettings::DEFAULT_ACCENT_COLOR);
});

it('reads the butchery deviation threshold from settings with 2 points by default [BR-11][C6]', function () {
    expect(app(CompanySettings::class)->deviationThresholdPercent())->toBe('2.00');

    app(UpdateSettingsAction::class)->execute([CompanySettings::DEVIATION_THRESHOLD_PERCENT => '3,5'], null);

    expect(app(CompanySettings::class)->deviationThresholdPercent())->toBe('3.50');
});

it('rejects a deviation threshold outside 0 to 100 [BR-11]', function () {
    expect(fn () => app(UpdateSettingsAction::class)->execute(
        [CompanySettings::DEVIATION_THRESHOLD_PERCENT => '120'], null
    ))->toThrow(InvalidArgumentException::class);

    expect(Setting::count())->toBe(0);
});

// M1 / S10: moneda por defecto configurable (USD o VES).
it('defaults the currency to USD without configuration [S8][S10]', function () {
    expect(app(CompanySettings::class)->defaultCurrency())->toBe('USD');
});

it('stores the default currency and audits the change [S7][S10]', function () {
    (new UpdateSettingsAction)->execute([CompanySettings::DEFAULT_CURRENCY => 'VES'], null);

    expect(app(CompanySettings::class)->defaultCurrency())->toBe('VES')
        ->and(AuditLog::where('auditable_type', 'setting')->count())->toBe(1);
});

it('rejects a currency other than USD or VES and changes nothing [S7][S10]', function (string $currency) {
    expect(fn () => (new UpdateSettingsAction)->execute([CompanySettings::DEFAULT_CURRENCY => $currency], null))
        ->toThrow(InvalidArgumentException::class);

    expect(Setting::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
})->with(['EUR', 'usd', '', 'VES ']);
