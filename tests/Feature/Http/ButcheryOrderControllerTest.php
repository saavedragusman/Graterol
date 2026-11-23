<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Catalog\Models\ProductPrice;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Domain\Settings\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-09 a BR-12: despiece en la PWA (Inertia).

function carniceroButcher(): User
{
    (new RolePermissionSeeder)->run();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'carnicero')->first());

    return $user;
}

function chickenBatchWithTemplate(User $user): array
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

    return compact('chicken', 'filete', 'batch');
}

it('lists available batches with remaining balance [BR-09]', function () {
    $user = carniceroButcher();
    $ctx = chickenBatchWithTemplate($user);

    $this->actingAs($user)
        ->get('/butchery/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('butchery/OrdersIndex')
            ->has('availableBatches', 1)
            ->where('availableBatches.0.id', $ctx['batch']->id)
        );
});

it('opens an order and redirects to its page [BR-09]', function () {
    $user = carniceroButcher();
    $ctx = chickenBatchWithTemplate($user);

    $response = $this->actingAs($user)
        ->post('/butchery/orders', ['batch_id' => $ctx['batch']->id, 'input_weight_kg' => '10.000']);

    $order = ButcheryOrder::firstOrFail();
    $response->assertRedirect("/butchery/orders/{$order->id}");
    expect($order->status)->toBe('open');
});

it('shows an open order with its frozen template lines [BR-09]', function () {
    $user = carniceroButcher();
    $ctx = chickenBatchWithTemplate($user);
    $order = (new OpenButcheryOrderAction)->execute($ctx['batch']->id, '10.000', $user->id);

    $this->actingAs($user)
        ->get("/butchery/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('butchery/OrderShow')
            ->where('order.id', $order->id)
            ->has('order.lines', 1)
        );
});

it('registers the weight of a cut and closes the order through the PWA [BR-10][BR-12]', function () {
    $user = carniceroButcher();
    $ctx = chickenBatchWithTemplate($user);
    $order = (new OpenButcheryOrderAction)->execute($ctx['batch']->id, '10.000', $user->id);
    $line = $order->lines->first();

    $this->actingAs($user)
        ->patch("/butchery/orders/{$order->id}/lines/{$line->id}", ['actual_weight_kg' => '9.000'])
        ->assertRedirect("/butchery/orders/{$order->id}");

    expect($line->fresh()->actual_weight_kg)->toBe('9.000');

    $this->actingAs($user)
        ->post("/butchery/orders/{$order->id}/close")
        ->assertRedirect('/butchery/orders');

    expect($order->fresh()->status)->toBe('closed');
});

it('rejects registering weight on a line that does not belong to this order [C14]', function () {
    $user = carniceroButcher();
    $ctxA = chickenBatchWithTemplate($user);
    $orderA = (new OpenButcheryOrderAction)->execute($ctxA['batch']->id, '10.000', $user->id);

    $chickenB = createRawMaterial('Pollo entero B');
    $filete = createCut('Filete B');
    (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chickenB->id,
        name: 'Pollo B',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );
    $batchB = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 2,
        productId: $chickenB->id,
        pieces: null,
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );
    $orderB = (new OpenButcheryOrderAction)->execute($batchB->id, '10.000', $user->id);
    $lineOfOrderB = $orderB->lines->first();

    $this->actingAs($user)
        ->patch("/butchery/orders/{$orderA->id}/lines/{$lineOfOrderB->id}", ['actual_weight_kg' => '5.000'])
        ->assertNotFound();

    expect($lineOfOrderB->fresh()->actual_weight_kg)->toBeNull();
});

it('rejects opening an order without the butchery.process permission [C14]', function () {
    (new RolePermissionSeeder)->run();
    $cajero = User::factory()->create();
    $cajero->roles()->attach(Role::where('name', 'cajero')->first());

    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'administrador')->first());
    $ctx = chickenBatchWithTemplate($admin);

    $this->actingAs($cajero)
        ->post('/butchery/orders', ['batch_id' => $ctx['batch']->id, 'input_weight_kg' => '10.000'])
        ->assertForbidden();
});
