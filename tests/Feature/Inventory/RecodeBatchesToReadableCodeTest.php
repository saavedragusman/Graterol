<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// BR-14/DEC-10 B/C18: la migración re-codifica los lotes existentes a L-#### sin
// que ningún movimiento ni orden pierda su lote (el kardex referencia batch_id).

function recodeMigration(): object
{
    return require database_path('migrations/2026_11_24_000002_recode_batches_to_readable_code.php');
}

function oldBatch(string $code, string $type, int $sourceId): int
{
    return DB::table('batches')->insertGetId([
        'code' => $code, 'source_type' => $type, 'source_id' => $sourceId, 'unit' => 'kg',
        'unit_cost' => '2.0000', 'total_cost' => '20.00', 'currency' => 'VES', 'exchange_rate' => '85.5000', 'received_at' => now(),
    ]);
}

/**
 * Tres lotes con código viejo (en un orden que no coincide con el número del código),
 * con movimientos, y dos órdenes de despiece que referencian lotes.
 *
 * @return array{ids: array<int, int>, oldCodes: array<int, string>}
 */
function seedOldFormatBatches(): array
{
    $userId = User::factory()->create()->id;
    $ids = [
        oldBatch('purchase_line-12', 'purchase_line', 12),
        oldBatch('butchery_order_line-5', 'butchery_order_line', 5),
        oldBatch('purchase_line-3', 'purchase_line', 3),
    ];

    foreach ([[$ids[0], 3], [$ids[1], 1], [$ids[2], 2]] as [$batchId, $count]) {
        for ($i = 0; $i < $count; $i++) {
            DB::table('inventory_movements')->insert([
                'batch_id' => $batchId, 'type' => $i === 0 ? 'purchase_in' : 'butchery_in', 'quantity' => $i === 0 ? '10.000' : '-1.000',
                'unit_cost' => '2.0000', 'user_id' => $userId, 'occurred_at' => now(),
            ]);
        }
    }

    $templateId = DB::table('butchery_templates')->insertGetId([
        'raw_material_product_id' => createRawMaterial()->id, 'name' => 'Plantilla', 'expected_shrinkage_percent' => '5.00', 'created_by' => $userId,
    ]);
    foreach ([['D-1', $ids[0]], ['D-2', $ids[0]], ['D-3', $ids[2]]] as [$code, $batchId]) {
        DB::table('butchery_orders')->insert([
            'code' => $code, 'batch_id' => $batchId, 'template_id' => $templateId, 'input_weight_kg' => '2.000',
            'expected_shrinkage_percent' => '5.00', 'butcher_id' => $userId, 'status' => 'open',
        ]);
    }

    return ['ids' => $ids, 'oldCodes' => ['purchase_line-12', 'butchery_order_line-5', 'purchase_line-3']];
}

function relationCounts(): array
{
    return [
        'movements' => DB::table('inventory_movements')->select('batch_id', DB::raw('count(*) as c'))->groupBy('batch_id')->orderBy('batch_id')->pluck('c', 'batch_id')->all(),
        'orders' => DB::table('butchery_orders')->select('batch_id', DB::raw('count(*) as c'))->groupBy('batch_id')->orderBy('batch_id')->pluck('c', 'batch_id')->all(),
        'movementIds' => DB::table('inventory_movements')->orderBy('id')->pluck('batch_id', 'id')->all(),
        'orderIds' => DB::table('butchery_orders')->orderBy('id')->pluck('batch_id', 'id')->all(),
    ];
}

it('re-codes existing batches to L-#### ordered by id, keeping every id [BR-14][C18]', function () {
    $seed = seedOldFormatBatches();

    recodeMigration()->up();

    $rows = DB::table('batches')->orderBy('id')->get(['id', 'code', 'legacy_code']);

    expect($rows->pluck('id')->all())->toBe($seed['ids'])
        ->and($rows->pluck('code')->all())->toBe(['L-0001', 'L-0002', 'L-0003'])
        ->and($rows->pluck('legacy_code')->all())->toBe($seed['oldCodes'])
        ->and($rows->pluck('code')->unique())->toHaveCount(3);
});

it('keeps every movement and every butchery order on the same batch [BR-14][C10][C18]', function () {
    seedOldFormatBatches();
    $before = relationCounts();

    recodeMigration()->up();

    expect(relationCounts())->toBe($before)
        ->and(DB::table('inventory_movements')->count())->toBe(6)
        ->and(DB::table('butchery_orders')->count())->toBe(3);
});

it('restores the original codes and drops legacy_code on down [BR-14][C18]', function () {
    $seed = seedOldFormatBatches();
    $migration = recodeMigration();
    $migration->up();

    $migration->down();

    expect(DB::table('batches')->orderBy('id')->pluck('code')->all())->toBe($seed['oldCodes'])
        ->and(Schema::hasColumn('batches', 'legacy_code'))->toBeFalse()
        ->and(relationCounts()['movementIds'])->toHaveCount(6);
});

it('changes nothing when run twice [BR-14][C18]', function () {
    seedOldFormatBatches();
    $migration = recodeMigration();
    $migration->up();
    $after = DB::table('batches')->orderBy('id')->get()->toArray();

    $migration->up();

    expect(DB::table('batches')->orderBy('id')->get()->toArray())->toEqual($after);
});

it('keeps batches that already have an L-digits code and numbers the old ones after the highest [BR-14][C18]', function () {
    $old = oldBatch('purchase_line-1', 'purchase_line', 1);
    $readable = oldBatch('L-0050', 'purchase_line', 2);
    $other = oldBatch('manual-lote', 'purchase', 3);

    recodeMigration()->up();

    $rows = DB::table('batches')->whereIn('id', [$old, $readable, $other])->orderBy('id')->get(['id', 'code', 'legacy_code'])->keyBy('id');

    expect($rows[$readable]->code)->toBe('L-0050')
        ->and($rows[$readable]->legacy_code)->toBeNull()
        ->and($rows[$old]->code)->toBe('L-0051')
        ->and($rows[$old]->legacy_code)->toBe('purchase_line-1')
        ->and($rows[$other]->code)->toBe('L-0052')
        ->and($rows[$other]->legacy_code)->toBe('manual-lote');
});

it('does not touch the kardex: the migration source never updates inventory_movements [C10]', function () {
    $source = (string) file_get_contents(database_path('migrations/2026_11_24_000002_recode_batches_to_readable_code.php'));

    expect(preg_match('/\b(UPDATE|DELETE\s+FROM)\s+(inventory_movements|"inventory_movements")/i', $source))->toBe(0);
});
