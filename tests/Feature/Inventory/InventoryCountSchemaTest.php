<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// M4 2.1 · DEC-05 (1 B), DEC-07: esquema del kardex ampliado y de las sesiones de conteo.

function columnInfo(string $table, string $column): ?object
{
    return DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', $table)
        ->where('column_name', $column)
        ->first();
}

function indexColumns(string $table): array
{
    return DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', $table)
        ->pluck('indexdef')
        ->all();
}

it('indexes inventory_movements by batch_id and type [BR-14]', function () {
    $defs = implode("\n", indexColumns('inventory_movements'));

    expect($defs)->toContain('(batch_id)')->and($defs)->toContain('(type)');
});

it('adds a nullable responsible_user_id foreign key to inventory_movements [BR-15, DEC-05]', function () {
    $column = columnInfo('inventory_movements', 'responsible_user_id');

    expect($column)->not->toBeNull()->and($column->is_nullable)->toBe('YES');

    $referenced = DB::selectOne(<<<'SQL'
        SELECT ccu.table_name AS foreign_table
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu ON kcu.constraint_name = tc.constraint_name
        JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY'
          AND tc.table_name = 'inventory_movements'
          AND kcu.column_name = 'responsible_user_id'
    SQL);

    expect($referenced?->foreign_table)->toBe('users');
});

function schemaTestMovement(): InventoryMovement
{
    $batch = Batch::create(['code' => 'L-0001', 'source_type' => 'purchase', 'unit' => 'kg', 'unit_cost' => '1.0000', 'received_at' => now()]);

    return InventoryMovement::create([
        'batch_id' => $batch->id, 'type' => 'purchase_in', 'quantity' => '1.000',
        'unit_cost' => '1.0000', 'user_id' => User::factory()->create()->id, 'occurred_at' => now(),
    ]);
}

it('accepts movements without responsible and still rejects UPDATE [C10]', function () {
    $movement = schemaTestMovement();

    // Los movimientos existentes quedan con responsable NULL.
    expect($movement->fresh()->responsible_user_id)->toBeNull();

    expect(fn () => DB::table('inventory_movements')->where('id', $movement->id)->update(['reason' => 'x']))
        ->toThrow(QueryException::class, 'es de solo inserción');
});

it('still rejects DELETE on inventory_movements [C10]', function () {
    $movement = schemaTestMovement();

    expect(fn () => DB::table('inventory_movements')->where('id', $movement->id)->delete())
        ->toThrow(QueryException::class, 'es de solo inserción');
});

it('creates inventory_counts with the documented columns [BR-16, DEC-07]', function () {
    expect(Schema::hasColumns('inventory_counts', [
        'id', 'code', 'status', 'notes', 'created_by', 'confirmed_by', 'confirmed_at', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(columnInfo('inventory_counts', 'notes')->is_nullable)->toBe('YES')
        ->and(columnInfo('inventory_counts', 'confirmed_by')->is_nullable)->toBe('YES')
        ->and(columnInfo('inventory_counts', 'confirmed_at')->is_nullable)->toBe('YES')
        ->and(columnInfo('inventory_counts', 'confirmed_at')->data_type)->toBe('timestamp with time zone')
        ->and(columnInfo('inventory_counts', 'created_by')->is_nullable)->toBe('NO');
});

it('rejects a duplicated inventory count code [BR-16]', function () {
    $user = User::factory()->create();
    $row = ['code' => 'C-0001', 'status' => 'draft', 'created_by' => $user->id];
    DB::table('inventory_counts')->insert($row);

    expect(fn () => DB::table('inventory_counts')->insert($row))->toThrow(QueryException::class);
});

it('creates inventory_count_lines with weights as numeric(12,3) [BR-16, C9]', function () {
    expect(Schema::hasColumns('inventory_count_lines', [
        'id', 'count_id', 'batch_id', 'system_quantity_kg', 'counted_weight_kg', 'difference_kg',
        'adjustment_movement_id', 'counted_by', 'counted_at', 'responsible_user_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    foreach (['system_quantity_kg', 'counted_weight_kg', 'difference_kg'] as $column) {
        $info = columnInfo('inventory_count_lines', $column);

        expect($info->numeric_precision)->toBe(12)
            ->and($info->numeric_scale)->toBe(3)
            ->and($info->is_nullable)->toBe('YES');
    }

    foreach (['adjustment_movement_id', 'counted_by', 'counted_at', 'responsible_user_id'] as $column) {
        expect(columnInfo('inventory_count_lines', $column)->is_nullable)->toBe('YES');
    }
});

it('allows one line per batch in each count only [BR-16]', function () {
    $user = User::factory()->create();
    $batch = Batch::create(['code' => 'L-0002', 'source_type' => 'purchase', 'unit' => 'kg', 'unit_cost' => '1.0000', 'received_at' => now()]);
    $countId = DB::table('inventory_counts')->insertGetId(['code' => 'C-0002', 'status' => 'draft', 'created_by' => $user->id]);
    $otherCountId = DB::table('inventory_counts')->insertGetId(['code' => 'C-0003', 'status' => 'draft', 'created_by' => $user->id]);

    DB::table('inventory_count_lines')->insert(['count_id' => $countId, 'batch_id' => $batch->id]);
    DB::table('inventory_count_lines')->insert(['count_id' => $otherCountId, 'batch_id' => $batch->id]);

    expect(fn () => DB::table('inventory_count_lines')->insert(['count_id' => $countId, 'batch_id' => $batch->id]))
        ->toThrow(QueryException::class);
});

it('seeds the inventory settings once without overwriting edits [BR-17, DEC-03, DEC-09]', function () {
    (new SettingSeeder)->run();

    expect(Setting::valueFor('inventory.allow_sale_without_stock', 'x'))->toBe('false')
        ->and(Setting::valueFor('inventory.expiry_alert_days', 'x'))->toBe('3');

    Setting::where('key', 'inventory.expiry_alert_days')->update(['value' => '5']);
    (new SettingSeeder)->run();

    expect(Setting::valueFor('inventory.expiry_alert_days', 'x'))->toBe('5')
        ->and(Setting::where('key', 'inventory.allow_sale_without_stock')->count())->toBe(1);
});
