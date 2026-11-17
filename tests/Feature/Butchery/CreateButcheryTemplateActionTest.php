<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-08: cada materia prima tiene una plantilla: cortes con % estándar + merma
// esperada, que suman 100 % (DEC-02: una activa por materia prima).
// Helpers createRawMaterial()/createCut(): tests/Pest.php

it('creates a template whose lines plus expected shrinkage sum exactly to 100 [BR-08]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');
    $muslo = createCut('Muslo');

    $template = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '3.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '60.00'],
            ['cut_product_id' => $muslo->id, 'expected_yield_percent' => '37.00'],
        ],
        createdByUserId: $user->id,
    );

    expect($template->is_active)->toBeTrue()
        ->and($template->lines)->toHaveCount(2);
});

it('rejects a template whose lines plus shrinkage do not sum to 100 [BR-08]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $create = fn () => (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo entero',
        expectedShrinkagePercent: '3.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '50.00'],
        ],
        createdByUserId: $user->id,
    );

    expect($create)->toThrow(InvalidArgumentException::class);
});

it('rejects a raw material product that is not type raw_material [BR-04][BR-08]', function () {
    $user = User::factory()->create();
    $notRawMaterial = createCut('Muslo'); // tipo derived
    $filete = createCut('Filete');

    $create = fn () => (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $notRawMaterial->id,
        name: 'Plantilla inválida',
        expectedShrinkagePercent: '0.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00'],
        ],
        createdByUserId: $user->id,
    );

    expect($create)->toThrow(InvalidArgumentException::class);
});

it('deactivates the previous active template for the same raw material [DEC-02]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $first = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo v1',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    $second = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo v2',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    expect($first->refresh()->is_active)->toBeFalse()
        ->and($second->is_active)->toBeTrue();
});

it('rejects the same cut twice in the same template [BR-08]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $create = fn () => (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo con corte repetido',
        expectedShrinkagePercent: '0.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '50.00'],
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '50.00'],
        ],
        createdByUserId: $user->id,
    );

    expect($create)->toThrow(QueryException::class);
});
