<?php

declare(strict_types=1);

use App\Domain\Inventory\MovementType;

// M4 2.2 · DEC-04, C10: tipos de movimiento del kardex como enum; los strings guardados no cambian.

it('keeps the stored string of every movement type [C10, DEC-04]', function () {
    expect(collect(MovementType::cases())->mapWithKeys(fn (MovementType $t) => [$t->name => $t->value])->all())->toBe([
        'PurchaseIn' => 'purchase_in',
        'PurchaseVoid' => 'purchase_void',
        'ButcheryIn' => 'butchery_in',
        'ButcheryOut' => 'butchery_out',
        'ButcheryVoid' => 'butchery_void',
        'ShrinkageDehydration' => 'shrinkage_dehydration',
        'ShrinkageSlicing' => 'shrinkage_slicing',
        'ShrinkageExpiry' => 'shrinkage_expiry',
        'ShrinkageConfiscation' => 'shrinkage_confiscation',
        'CountAdjustment' => 'count_adjustment',
        'SaleOut' => 'sale_out',
    ]);
});

it('gives every type a Spanish label, an icon and a tone [BR-15]', function () {
    $tones = ['accent', 'success', 'warning', 'info', 'neutral'];

    foreach (MovementType::cases() as $type) {
        expect(trim($type->label()))->not->toBe('', $type->value.' sin label')
            ->and($type->icon())->toStartWith('heroicon-o-', $type->value.' sin icon')
            ->and($tones)->toContain($type->tone());
    }

    expect(collect(MovementType::cases())->map->label()->unique())->toHaveCount(count(MovementType::cases()));
});

it('labels the typed shrinkages of BR-15 separately, including confiscation [DEC-04]', function () {
    expect(MovementType::ShrinkageDehydration->label())->toBe('Merma por deshidratación')
        ->and(MovementType::ShrinkageSlicing->label())->toBe('Merma por rebanado')
        ->and(MovementType::ShrinkageExpiry->label())->toBe('Merma por vencimiento')
        ->and(MovementType::ShrinkageConfiscation->label())->toBe('Merma por decomiso')
        ->and(MovementType::CountAdjustment->label())->toBe('Ajuste por conteo');
});

it('resolves a type from its stored string', function () {
    expect(MovementType::from('purchase_in'))->toBe(MovementType::PurchaseIn)
        ->and(MovementType::tryFrom('nope'))->toBeNull();
});

it('writes movement types through the enum in the existing actions [C10]', function () {
    $files = [
        'app/Domain/Inventory/Actions/CreateLotFromPurchaseLineAction.php',
        'app/Domain/Inventory/Actions/CreateLotFromButcheryOutputAction.php',
        'app/Domain/Butchery/Actions/OpenButcheryOrderAction.php',
        'app/Domain/Butchery/Actions/VoidButcheryOrderAction.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents(base_path($file));

        foreach (MovementType::cases() as $type) {
            expect($source)->not->toContain("'{$type->value}'", "{$file} escribe '{$type->value}' en literal");
        }

        expect($source)->toContain('MovementType::');
    }
});
