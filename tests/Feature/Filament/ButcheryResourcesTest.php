<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CloseButcheryOrderAction;
use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\RegisterButcheryOutputWeightAction;
use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Catalog\Models\ProductPrice;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Settings\Models\Role;
use App\Filament\Resources\ButcheryOrderResource\Pages\ListButcheryOrders;
use App\Filament\Resources\ButcheryOrderResource\Pages\ViewButcheryOrder;
use App\Filament\Resources\ButcheryTemplateResource;
use App\Filament\Resources\ButcheryTemplateResource\Pages\CreateButcheryTemplate;
use App\Filament\Resources\ButcheryTemplateResource\Pages\EditButcheryTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// BR-08 a BR-12: Filament gestiona plantillas y consulta/anula órdenes; la
// operación de pesar y cerrar vive en la PWA (M2 reparte Purchasing igual).

function bfUser(string $role): User
{
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->first());
    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

function bfOrderWithOutput(User $user): ButcheryOrder
{
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    ProductPrice::create(['product_id' => $filete->id, 'price_list' => 'detail', 'amount' => '5.00', 'currency' => 'USD']);

    (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 1,
        productId: $chicken->id,
        pieces: null,
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $order = (new OpenButcheryOrderAction)->execute(batchId: $batch->id, inputWeightKg: '10.000', butcherId: $user->id);
    $line = $order->lines->first();
    (new RegisterButcheryOutputWeightAction)->execute($line->id, '9.000');

    return (new CloseButcheryOrderAction)->execute($order->id, $user->id);
}

it('creates a template with its lines through CreateButcheryTemplateAction [BR-08]', function () {
    $user = bfUser('administrador');
    $chicken = createRawMaterial();
    $filete = createCut('Filete');
    $muslo = createCut('Muslo');

    Livewire::test(CreateButcheryTemplate::class)
        ->fillForm([
            'raw_material_product_id' => $chicken->id,
            'name' => 'Pollo entero',
            'expected_shrinkage_percent' => '3.00',
            'lines' => [
                ['cut_product_id' => $filete->id, 'expected_yield_percent' => '60.00'],
                ['cut_product_id' => $muslo->id, 'expected_yield_percent' => '37.00'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = ButcheryTemplate::where('name', 'Pollo entero')->firstOrFail();
    expect($template->is_active)->toBeTrue()
        ->and($template->created_by)->toBe($user->id)
        ->and($template->lines)->toHaveCount(2);
});

it('rejects a template whose percentages do not sum 100 from the panel [BR-08]', function () {
    bfUser('administrador');
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    Livewire::test(CreateButcheryTemplate::class)
        ->fillForm([
            'raw_material_product_id' => $chicken->id,
            'name' => 'Pollo roto',
            'expected_shrinkage_percent' => '0.00',
            'lines' => [['cut_product_id' => $filete->id, 'expected_yield_percent' => '50.00']],
        ])
        ->call('create');

    expect(ButcheryTemplate::where('name', 'Pollo roto')->exists())->toBeFalse();
});

it('edits a template in place through UpdateButcheryTemplateAction [DEC-03]', function () {
    $user = bfUser('administrador');
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $template = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo v1',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    Livewire::test(EditButcheryTemplate::class, ['record' => $template->getKey()])
        ->fillForm([
            'name' => 'Pollo calibrado',
            'expected_shrinkage_percent' => '5.00',
            'lines' => [['cut_product_id' => $filete->id, 'expected_yield_percent' => '95.00']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->name)->toBe('Pollo calibrado')
        ->and($template->fresh()->expected_shrinkage_percent)->toBe('5.00');
});

it('does not let carnicero create templates from the panel [BR-03]', function () {
    bfUser('carnicero');

    expect(ButcheryTemplateResource::canCreate())->toBeFalse();
});

it('shows a closed order with its lines, deviation and allocated cost [BR-09 to BR-12]', function () {
    $user = bfUser('administrador');
    $order = bfOrderWithOutput($user);

    Livewire::test(ViewButcheryOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful()
        ->assertSee('Filete')
        ->assertSee('9.000')
        ->assertSee('-10.00');
});

it('voids a closed order only with a reason and a proper permission [BR-03][C12]', function () {
    $admin = bfUser('administrador');
    $order = bfOrderWithOutput($admin);

    Livewire::test(ViewButcheryOrder::class, ['record' => $order->getKey()])
        ->callAction('void', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
    expect($order->fresh()->status)->toBe('closed');

    Livewire::test(ViewButcheryOrder::class, ['record' => $order->getKey()])
        ->callAction('void', ['reason' => 'Pesaje incorrecto'])
        ->assertHasNoActionErrors();

    $order->refresh();
    expect($order->status)->toBe('voided')
        ->and($order->void_reason)->toBe('Pesaje incorrecto')
        ->and($order->voided_by)->toBe($admin->id);
});

it('hides the void action for users without butchery.void [BR-03]', function () {
    $admin = bfUser('administrador');
    $order = bfOrderWithOutput($admin);

    bfUser('carnicero');

    Livewire::test(ViewButcheryOrder::class, ['record' => $order->getKey()])->assertActionHidden('void');
});

it('lists orders and filters them by status', function () {
    $admin = bfUser('administrador');
    $order = bfOrderWithOutput($admin);

    Livewire::test(ListButcheryOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->filterTable('status', 'closed')
        ->assertCanSeeTableRecords([$order]);
});

it('shows the allocated cost in USD with the Bs line using the lot frozen rate [C13][BR-02]', function () {
    $user = bfUser('administrador');
    $order = bfOrderWithOutput($user);

    // Lote en Bs 20,00 con tasa congelada 85,50 → $ 0,23 arriba y Bs 20,00 debajo.
    Livewire::test(ViewButcheryOrder::class, ['record' => $order->getKey()])
        ->assertSee('$ 0,23')
        ->assertSee('Bs 20,00');
});
