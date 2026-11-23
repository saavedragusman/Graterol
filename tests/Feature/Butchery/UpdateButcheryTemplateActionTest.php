<?php

declare(strict_types=1);

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Domain\Butchery\Actions\UpdateButcheryTemplateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BR-08/DEC-03: la plantilla se edita en el sitio (calibración); las órdenes
// ya abiertas congelan su propio % y no cambian si la plantilla se edita después.

it('edits the template lines and shrinkage in place, re-validating the 100% sum [BR-08][DEC-03]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');
    $muslo = createCut('Muslo');

    $template = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo v1',
        expectedShrinkagePercent: '3.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '60.00'],
            ['cut_product_id' => $muslo->id, 'expected_yield_percent' => '37.00'],
        ],
        createdByUserId: $user->id,
    );

    $updated = (new UpdateButcheryTemplateAction)->execute(
        templateId: $template->id,
        name: 'Pollo calibrado',
        expectedShrinkagePercent: '5.00',
        lines: [
            ['cut_product_id' => $filete->id, 'expected_yield_percent' => '58.00'],
            ['cut_product_id' => $muslo->id, 'expected_yield_percent' => '37.00'],
        ],
    );

    expect($updated->name)->toBe('Pollo calibrado')
        ->and($updated->expected_shrinkage_percent)->toBe('5.00')
        ->and($updated->lines)->toHaveCount(2)
        ->and($updated->id)->toBe($template->id);
});

it('rejects an edit whose lines plus shrinkage do not sum to 100 [BR-08]', function () {
    $user = User::factory()->create();
    $chicken = createRawMaterial();
    $filete = createCut('Filete');

    $template = (new CreateButcheryTemplateAction)->execute(
        rawMaterialProductId: $chicken->id,
        name: 'Pollo v1',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '100.00']],
        createdByUserId: $user->id,
    );

    $update = fn () => (new UpdateButcheryTemplateAction)->execute(
        templateId: $template->id,
        name: 'Pollo roto',
        expectedShrinkagePercent: '0.00',
        lines: [['cut_product_id' => $filete->id, 'expected_yield_percent' => '50.00']],
    );

    expect($update)->toThrow(InvalidArgumentException::class);
});
