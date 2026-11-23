<?php

declare(strict_types=1);

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\Models\Setting;
use App\Filament\Pages\ManageCompanySettings;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// M1 / C6: la empresa se configura desde la oficina; solo con settings.manage.

function csUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

function validCompanyForm(array $overrides = []): array
{
    return [
        'name' => 'Carnes Demo',
        'brand_color' => '#123456',
        'accent_color' => '#ABCDEF',
        'charcuteria_vat_rate' => '8,5',
        'deviation_threshold_percent' => '3',
        'default_currency' => 'VES',
        ...$overrides,
    ];
}

it('lets the administrator save the company identity and audits it [C6][C15]', function () {
    $user = csUser('administrador');

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(validCompanyForm())
        ->call('save')
        ->assertHasNoFormErrors();

    $company = app(CompanySettings::class);
    expect($company->name())->toBe('Carnes Demo')
        ->and($company->brandColor())->toBe('#123456')
        ->and($company->accentColor())->toBe('#ABCDEF')
        ->and($company->charcuteriaVatRate())->toBe('8.50')
        ->and($company->deviationThresholdPercent())->toBe('3.00')
        ->and($company->defaultCurrency())->toBe('VES')
        ->and(AuditLog::where('auditable_type', 'setting')->where('user_id', $user->id)->count())->toBe(6);
});

it('shows the current values when opened [C6]', function () {
    csUser('administrador');
    Setting::create(['key' => CompanySettings::NAME, 'value' => 'Carnes Demo']);

    Livewire::test(ManageCompanySettings::class)
        ->assertFormSet([
            'name' => 'Carnes Demo',
            'brand_color' => CompanySettings::DEFAULT_BRAND_COLOR,
            'charcuteria_vat_rate' => '16.00',
            'default_currency' => 'USD',
        ]);
});

it('forbids users without settings.manage [C14]', function () {
    csUser('cajero');

    $this->get(ManageCompanySettings::getUrl())->assertForbidden();
});

it('rejects invalid values and leaves stored data unchanged [C6]', function (array $overrides, string $field) {
    csUser('administrador');

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(validCompanyForm($overrides))
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(Setting::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
})->with([
    'malformed brand color' => [['brand_color' => 'rojo'], 'brand_color'],
    'empty name' => [['name' => ''], 'name'],
    'vat over 100' => [['charcuteria_vat_rate' => '101'], 'charcuteria_vat_rate'],
    'threshold not numeric' => [['deviation_threshold_percent' => 'dos'], 'deviation_threshold_percent'],
    'currency not allowed' => [['default_currency' => 'EUR'], 'default_currency'],
]);

it('stores the uploaded logo on the public disk [C6]', function () {
    Storage::fake('public');
    csUser('administrador');

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(validCompanyForm(['logo_path' => UploadedFile::fake()->image('logo.png', 200, 80)]))
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(CompanySettings::class)->logoPath();
    expect($path)->toStartWith(CompanySettings::LOGO_DIRECTORY.'/');
    Storage::disk('public')->assertExists($path);
});

it('rejects a logo that is not an image [C6]', function () {
    Storage::fake('public');
    csUser('administrador');

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(validCompanyForm(['logo_path' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]))
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    expect(Setting::where('key', CompanySettings::LOGO_PATH)->exists())->toBeFalse();
});
