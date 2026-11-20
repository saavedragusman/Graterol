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
