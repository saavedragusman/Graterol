<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Role;
use App\Domain\Settings\PermissionKey;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// S8/S6 (C14, C15): auditoría de solo lectura, visible con audit.view.

function auditUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

function auditRow(array $attributes): AuditLog
{
    $log = AuditLog::create($attributes + ['auditable_id' => 1]);
    if (isset($attributes['created_at'])) {
        $log->forceFill(['created_at' => $attributes['created_at']])->save();
    }

    return $log;
}

it('lists audit rows newest first with Spanish labels', function () {
    $admin = auditUser('administrador');
    $old = auditRow(['auditable_type' => 'purchase', 'action' => 'purchase.void', 'user_id' => $admin->id, 'reason' => 'Error de carga', 'before' => ['status' => 'received'], 'after' => ['status' => 'voided'], 'created_at' => now()->subDay()]);
    $new = auditRow(['auditable_type' => 'setting', 'action' => 'updated', 'user_id' => null, 'before' => ['key' => 'company_name', 'value' => 'A'], 'after' => ['key' => 'company_name', 'value' => 'B'], 'created_at' => now()]);

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('Compra')
        ->assertSee('Anulación')
        ->assertSee('Configuración')
        ->assertSee('Cambio')
        ->assertSee('company_name')
        ->assertSee('Sistema')
        ->assertSee('Error de carga');
});

it('forbids a cajero from the audit list [C14]', function () {
    auditUser('cajero');

    $this->get(AuditLogResource::getUrl('index'))->assertForbidden();
});

it('filters by type and by user', function () {
    $admin = auditUser('administrador');
    $other = User::factory()->create();
    $purchase = auditRow(['auditable_type' => 'purchase', 'action' => 'purchase.void', 'user_id' => $admin->id]);
    $order = auditRow(['auditable_type' => 'butchery_order', 'action' => 'butchery_order.void', 'user_id' => $other->id]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('auditable_type', 'purchase')
        ->assertCanSeeTableRecords([$purchase])
        ->assertCanNotSeeTableRecords([$order])
        ->resetTableFilters()
        ->filterTable('user_id', $other->id)
        ->assertCanSeeTableRecords([$order])
        ->assertCanNotSeeTableRecords([$purchase]);
});

it('shows before and after in the Ver modal for a setting change', function () {
    auditUser('administrador');
    $log = auditRow(['auditable_type' => 'setting', 'action' => 'updated', 'before' => ['key' => 'company_name', 'value' => 'Valor viejo'], 'after' => ['key' => 'company_name', 'value' => 'Valor nuevo']]);

    Livewire::test(ListAuditLogs::class)
        ->mountTableAction('view', $log)
        ->call('forceRender') // el modal se renderiza como partial; se fuerza el render completo para inspeccionarlo
        ->assertSee('Antes')
        ->assertSee('Después')
        ->assertSee('Valor viejo')
        ->assertSee('Valor nuevo');
});

it('shows before and after in the Ver modal for a purchase void', function () {
    auditUser('administrador');
    $log = auditRow(['auditable_type' => 'purchase', 'action' => 'purchase.void', 'reason' => 'Duplicada', 'before' => ['status' => 'received', 'lines' => [['qty' => '5.000']]], 'after' => ['status' => 'voided']]);

    Livewire::test(ListAuditLogs::class)
        ->mountTableAction('view', $log)
        ->call('forceRender')
        ->assertSee('received')
        ->assertSee('voided')
        ->assertSee('Duplicada')
        ->assertSee('5.000')
        ->assertSee('—');
});

it('escapes values in the modal', function () {
    auditUser('administrador');
    $log = auditRow(['auditable_type' => 'setting', 'action' => 'updated', 'before' => ['value' => '<script>alert(1)</script>'], 'after' => ['value' => 'x']]);

    Livewire::test(ListAuditLogs::class)
        ->mountTableAction('view', $log)
        ->call('forceRender')
        ->assertSee('x')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

it('is read-only: no create or edit pages and the policy denies writes [C15]', function () {
    $admin = auditUser('administrador');
    $log = auditRow(['auditable_type' => 'purchase', 'action' => 'purchase.void']);

    expect(array_keys(AuditLogResource::getPages()))->toBe(['index'])
        ->and($admin->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($admin->can('view', $log))->toBeTrue()
        ->and($admin->can('create', AuditLog::class))->toBeFalse()
        ->and($admin->can('update', $log))->toBeFalse()
        ->and($admin->can('delete', $log))->toBeFalse()
        ->and($admin->can('restore', $log))->toBeFalse()
        ->and($admin->can('forceDelete', $log))->toBeFalse()
        ->and(PermissionKey::AuditView->value)->toBe('audit.view');
});
