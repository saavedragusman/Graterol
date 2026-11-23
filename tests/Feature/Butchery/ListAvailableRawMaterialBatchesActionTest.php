<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\ListAvailableRawMaterialBatchesAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Actions\CreateLotFromPurchaseLineAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-09/DEC-07: lotes de materia prima con saldo disponible para despiece.

it('lists only raw material batches with a positive balance [BR-09]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $resale = Product::create([
        'name' => 'Refresco',
        'product_type' => 'resale',
        'unit' => 'kg',
        'product_category' => 'other',
        'tax_rate' => 16,
        'is_tax_exempt' => false,
        'active' => true,
    ]);

    $chickenBatch = (new CreateLotFromPurchaseLineAction)->execute(
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

    (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 2,
        productId: $resale->id,
        pieces: null,
        receivedWeightKg: '5.000',
        totalCost: '10.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    $available = (new ListAvailableRawMaterialBatchesAction)->execute();

    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe($chickenBatch->id)
        ->and($available->first()->getAttribute('balance'))->toBe('10.000');
});

it('excludes a raw material batch already fully consumed by despiece [DEC-07]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    $batch = (new CreateLotFromPurchaseLineAction)->execute(
        sourceType: 'purchase_line',
        sourceId: 3,
        productId: $chicken->id,
        pieces: null,
        receivedWeightKg: '10.000',
        totalCost: '20.0000',
        currency: 'VES',
        exchangeRate: '85.5000',
        userId: $user->id,
    );

    (new OpenButcheryOrderAction)->execute($batch->id, '10.000', $user->id);

    expect((new ListAvailableRawMaterialBatchesAction)->execute())->toHaveCount(0);
});
