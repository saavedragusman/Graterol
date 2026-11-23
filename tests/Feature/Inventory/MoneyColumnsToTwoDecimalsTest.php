<?php

declare(strict_types=1);

use App\Domain\Purchasing\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// S6/S8 (M1 dinero a 2 decimales): la migración pasa los MONTOS y la tasa congelada de
// numeric(18,4) a numeric(18,2), redondeando half-up en sitio y repartiendo el residuo de
// centavos para que los totales sigan cuadrando. Los valores unitarios (S5) y el kardex
// inmutable no se tocan. PostgreSQL permite DDL dentro de la transacción de la prueba, así
// que se ensancha el esquema con `down()`, se siembran filas con 4 decimales y se corre `up()`.

const MONEY_COLUMNS = [
    ['purchase_lines', 'line_amount'],
    ['purchase_lines', 'allocated_freight'],
    ['purchase_lines', 'allocated_other_costs'],
    ['purchases', 'freight_amount'],
    ['purchases', 'other_costs_amount'],
    ['purchases', 'exchange_rate'],
    ['batches', 'total_cost'],
    ['batches', 'exchange_rate'],
    ['butchery_order_lines', 'allocated_cost'],
    ['exchange_rates', 'rate'],
];

const UNIT_COLUMNS = [
    ['batches', 'unit_cost'],
    ['inventory_movements', 'unit_cost'],
    ['product_prices', 'amount'],
    ['butchery_order_lines', 'reference_price_per_kg'],
];

function moneyMigration(): object
{
    return require database_path('migrations/2026_11_20_000004_money_columns_to_two_decimals.php');
}

function columnScale(string $table, string $column): int
{
    return (int) DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', $table)
        ->where('column_name', $column)
        ->value('numeric_scale');
}

function legacyBatch(string $code, string $sourceType, ?int $sourceId, ?int $productId, string $unitCost, string $totalCost, string $rate): int
{
    return DB::table('batches')->insertGetId([
        'code' => $code,
        'product_id' => $productId,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'unit' => 'kg',
        'unit_cost' => $unitCost,
        'total_cost' => $totalCost,
        'currency' => 'VES',
        'exchange_rate' => $rate,
        'received_at' => now(),
    ]);
}

/**
 * Siembra, con el esquema ya ensanchado a 4 decimales, datos "de antes" del cambio.
 *
 * @return array<string, mixed>
 */
function seedLegacyMoney(): array
{
    $userId = User::factory()->create()->id;
    $supplierId = Supplier::factory()->create()->id;
    $rawId = createRawMaterial()->id;

    DB::table('exchange_rates')->insert([
        ['rate' => '85.5050', 'source' => 'manual', 'effective_at' => now()],
        ['rate' => '36.4949', 'source' => 'manual', 'effective_at' => now()],
    ]);

    // Compra recibida: flete 10.0000 repartido 3.3334 / 3.3333 / 3.3333 (redondeado: 9.99, falta 0.01)
    // y otros costos 1.0050 repartido 0.5025 / 0.3015 / 0.2010 (redondeado: 1.00, el total sube a 1.01).
    $purchaseId = DB::table('purchases')->insertGetId([
        'supplier_id' => $supplierId,
        'supplier_invoice_number' => 'F-1',
        'supplier_invoice_date' => now()->toDateString(),
        'currency' => 'VES',
        'exchange_rate' => '85.5050',
        'freight_amount' => '10.0000',
        'other_costs_amount' => '1.0050',
        'status' => 'received',
    ]);

    $lineSpecs = [
        // peso recibido, monto, flete, otros
        ['50.000', '100.0050', '3.3334', '0.5025'],
        ['30.000', '60.0000', '3.3333', '0.3015'],
        ['20.000', '40.0049', '3.3333', '0.2010'],
    ];
    $purchaseBatchIds = [];
    $purchaseLineIds = [];

    foreach ($lineSpecs as $i => [$weight, $amount, $freight, $other]) {
        $lineId = DB::table('purchase_lines')->insertGetId([
            'purchase_id' => $purchaseId,
            'product_id' => $rawId,
            'invoiced_weight_kg' => $weight,
            'line_amount' => $amount,
            'received_weight_kg' => $weight,
            'allocated_freight' => $freight,
            'allocated_other_costs' => $other,
        ]);
        // Lotes 1 y 2: total viejo = monto + flete + otros. Lote 3: guardado sin flete (solo el monto).
        $legacyTotal = ['103.8409', '63.6348', '40.0049'][$i];
        $batchId = legacyBatch("purchase_line-{$lineId}", 'purchase_line', $lineId, $rawId, '2.2222', $legacyTotal, '85.5050');
        DB::table('purchase_lines')->where('id', $lineId)->update(['batch_id' => $batchId]);
        $purchaseLineIds[$i] = $lineId;
        $purchaseBatchIds[$i] = $batchId;
    }

    // Compra en borrador: sin reparto ni lote; solo se redondean sus montos.
    $draftId = DB::table('purchases')->insertGetId([
        'supplier_id' => $supplierId,
        'supplier_invoice_number' => 'F-2',
        'supplier_invoice_date' => now()->toDateString(),
        'currency' => 'VES',
        'exchange_rate' => '85.5050',
        'freight_amount' => '2.3350',
        'other_costs_amount' => '0.0000',
        'status' => 'draft',
    ]);
    $draftLineId = DB::table('purchase_lines')->insertGetId([
        'purchase_id' => $draftId,
        'product_id' => $rawId,
        'invoiced_weight_kg' => '10.000',
        'line_amount' => '10.0050',
    ]);

    // Despiece cerrado: costos asignados 24.5738 / 19.1550 / 5.5100 / 4.1650 / 2.3600 / 0.2362
    // (Σ = 56.0000). Redondeados suman 56.01: el centavo sobrante se resta al corte de mayor valor.
    $inputBatchId = legacyBatch('input-1', 'purchase_line', null, $rawId, '4.0000', '56.0000', '85.5050');
    $templateId = DB::table('butchery_templates')->insertGetId([
        'raw_material_product_id' => $rawId,
        'name' => 'Plantilla',
        'expected_shrinkage_percent' => '5.00',
        'created_by' => $userId,
    ]);
    $orderId = DB::table('butchery_orders')->insertGetId([
        'code' => 'ORD-1',
        'batch_id' => $inputBatchId,
        'template_id' => $templateId,
        'input_weight_kg' => '14.000',
        'expected_shrinkage_percent' => '5.00',
        'butcher_id' => $userId,
        'status' => 'closed',
    ]);

    $cutSpecs = [
        // peso real, precio de referencia por kg (valor = peso × precio), costo asignado
        ['6.000', '5.0000', '24.5738'],
        ['4.000', '4.0000', '19.1550'],
        ['1.500', '3.0000', '5.5100'],
        ['1.000', '4.0000', '4.1650'],
        ['0.800', '3.0000', '2.3600'],
        ['0.100', '2.0000', '0.2362'],
    ];
    $orderLineIds = [];
    $outputBatchIds = [];

    foreach ($cutSpecs as $i => [$weight, $price, $cost]) {
        $cutId = createCut("Corte {$i}")->id;
        $lineId = DB::table('butchery_order_lines')->insertGetId([
            'order_id' => $orderId,
            'cut_product_id' => $cutId,
            'expected_yield_percent' => '10.00',
            'expected_weight_kg' => $weight,
            'actual_weight_kg' => $weight,
            'reference_price_per_kg' => $price,
            'allocated_cost' => $cost,
        ]);
        $outputBatchId = legacyBatch("butchery_order_line-{$lineId}", 'butchery_order_line', $lineId, $cutId, '3.3333', '777.7777', '85.5050');
        DB::table('butchery_order_lines')->where('id', $lineId)->update(['output_batch_id' => $outputBatchId]);

        DB::table('inventory_movements')->insert([
            'batch_id' => $outputBatchId,
            'type' => 'butchery_out',
            'quantity' => $weight,
            'unit_cost' => '3.3333',
            'user_id' => $userId,
            'reference_type' => 'butchery_order_line',
            'reference_id' => $lineId,
            'occurred_at' => now(),
        ]);
        $orderLineIds[$i] = $lineId;
        $outputBatchIds[$i] = $outputBatchId;
    }

    DB::table('inventory_movements')->insert([
        'batch_id' => $purchaseBatchIds[0],
        'type' => 'purchase_in',
        'quantity' => '50.000',
        'unit_cost' => '2.0001',
        'user_id' => $userId,
        'occurred_at' => now(),
    ]);

    $productId = $rawId;
    DB::table('product_prices')->insert([
        'product_id' => $productId,
        'price_list' => 'detail',
        'amount' => '1.8315',
    ]);

    return compact('purchaseId', 'purchaseLineIds', 'purchaseBatchIds', 'draftId', 'draftLineId', 'orderId', 'orderLineIds', 'outputBatchIds');
}

function kardexSnapshot(): string
{
    return json_encode(DB::table('inventory_movements')->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR);
}

it('widens the money and rate columns back to 4 decimals on down() and keeps unit columns at 4', function () {
    moneyMigration()->down();

    foreach (MONEY_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(4, "{$table}.{$column}");
    }
    foreach (UNIT_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(4, "{$table}.{$column}");
    }
});

it('moves every money and rate column to 2 decimals and keeps unit columns at 4 [S5, S6, S8]', function () {
    expect(columnScale('purchases', 'exchange_rate'))->toBe(2);

    foreach (MONEY_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(2, "{$table}.{$column}");
    }
    foreach (UNIT_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(4, "{$table}.{$column}");
    }
});

it('rounds legacy 4-decimal amounts half-up and splits the cent residue so allocations still add up [S6, BR-01, BR-12]', function () {
    $migration = moneyMigration();
    $migration->down();
    $ids = seedLegacyMoney();
    $kardexBefore = kardexSnapshot();

    $migration->up();

    foreach (MONEY_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(2, "{$table}.{$column}");
    }
    foreach (UNIT_COLUMNS as [$table, $column]) {
        expect(columnScale($table, $column))->toBe(4, "{$table}.{$column}");
    }

    // Tasas: half-up a 2 decimales (85.5050 sube a 85.51; 36.4949 baja a 36.49).
    expect(DB::table('exchange_rates')->orderBy('id')->pluck('rate')->all())->toBe(['85.51', '36.49'])
        ->and(DB::table('purchases')->where('id', $ids['purchaseId'])->value('exchange_rate'))->toBe('85.51');

    // Compra recibida: flete 10.00 y otros costos 1.01 exactos; el residuo va a la línea de mayor peso (la primera).
    $purchase = DB::table('purchases')->where('id', $ids['purchaseId'])->first();
    $lines = DB::table('purchase_lines')->where('purchase_id', $ids['purchaseId'])->orderBy('id')->get();

    expect($purchase->freight_amount)->toBe('10.00')
        ->and($purchase->other_costs_amount)->toBe('1.01')
        ->and($lines->pluck('allocated_freight')->all())->toBe(['3.34', '3.33', '3.33'])
        ->and($lines->pluck('allocated_other_costs')->all())->toBe(['0.51', '0.30', '0.20'])
        ->and($lines->pluck('line_amount')->all())->toBe(['100.01', '60.00', '40.00'])
        ->and((string) DB::table('purchase_lines')->where('purchase_id', $ids['purchaseId'])->sum('allocated_freight'))->toBe('10.00')
        ->and((string) DB::table('purchase_lines')->where('purchase_id', $ids['purchaseId'])->sum('allocated_other_costs'))->toBe('1.01');

    // Lotes 1 y 2 eran la suma completa: se reconcilian con las partes redondeadas.
    // El lote 3 se guardó sin flete: solo se redondea su total (40.0049 → 40.00), no se le suma el flete.
    expect(DB::table('batches')->whereIn('id', $ids['purchaseBatchIds'])->orderBy('id')->pluck('total_cost')->all())
        ->toBe(['103.86', '63.63', '40.00'])
        ->and(DB::table('batches')->whereIn('id', $ids['purchaseBatchIds'])->orderBy('id')->pluck('exchange_rate')->unique()->all())->toBe(['85.51']);

    // Compra en borrador: se redondea y lo no asignado sigue nulo.
    $draft = DB::table('purchases')->where('id', $ids['draftId'])->first();
    $draftLine = DB::table('purchase_lines')->where('id', $ids['draftLineId'])->first();
    expect($draft->freight_amount)->toBe('2.34')
        ->and($draft->other_costs_amount)->toBe('0.00')
        ->and($draftLine->line_amount)->toBe('10.01')
        ->and($draftLine->allocated_freight)->toBeNull()
        ->and($draftLine->allocated_other_costs)->toBeNull();

    // Despiece: 24.57 / 19.16 / 5.51 / 4.17 / 2.36 / 0.24 suman 56.01; el corte de mayor valor cede 0.01 y Σ = 56.00.
    $allocated = DB::table('butchery_order_lines')->where('order_id', $ids['orderId'])->orderBy('id')->pluck('allocated_cost')->all();
    expect($allocated)->toBe(['24.56', '19.16', '5.51', '4.17', '2.36', '0.24'])
        ->and((string) DB::table('butchery_order_lines')->where('order_id', $ids['orderId'])->sum('allocated_cost'))->toBe('56.00');

    // Lote de salida = costo asignado de su corte ya redondeado.
    expect(DB::table('batches')->whereIn('id', $ids['outputBatchIds'])->orderBy('id')->pluck('total_cost')->all())->toBe($allocated);

    // Valores unitarios y kardex intactos (S5, C10).
    expect(DB::table('batches')->whereIn('id', $ids['outputBatchIds'])->pluck('unit_cost')->unique()->all())->toBe(['3.3333'])
        ->and(DB::table('butchery_order_lines')->where('order_id', $ids['orderId'])->orderBy('id')->pluck('reference_price_per_kg')->all())
        ->toBe(['5.0000', '4.0000', '3.0000', '4.0000', '3.0000', '2.0000'])
        ->and(DB::table('product_prices')->value('amount'))->toBe('1.8315')
        ->and(kardexSnapshot())->toBe($kardexBefore);
});

it('leaves the immutable kardex trigger in place', function () {
    moneyMigration()->down();
    moneyMigration()->up();

    expect(DB::table('pg_trigger')->where('tgname', 'trg_inventory_movements_immutable')->where('tgenabled', 'O')->count())->toBe(1);
});

it('is a no-op on rows that already have 2 decimals', function () {
    $ids = seedLegacyMoney2dp();

    $before = [
        DB::table('purchase_lines')->orderBy('id')->get()->all(),
        DB::table('batches')->orderBy('id')->get()->all(),
        DB::table('butchery_order_lines')->orderBy('id')->get()->all(),
    ];

    moneyMigration()->up();

    expect([
        DB::table('purchase_lines')->orderBy('id')->get()->all(),
        DB::table('batches')->orderBy('id')->get()->all(),
        DB::table('butchery_order_lines')->orderBy('id')->get()->all(),
    ])->toEqual($before)->and($ids)->not->toBeEmpty();
});

function seedLegacyMoney2dp(): array
{
    $supplierId = Supplier::factory()->create()->id;
    $rawId = createRawMaterial()->id;
    $purchaseId = DB::table('purchases')->insertGetId([
        'supplier_id' => $supplierId,
        'supplier_invoice_number' => 'F-9',
        'supplier_invoice_date' => now()->toDateString(),
        'currency' => 'VES',
        'exchange_rate' => '85.51',
        'freight_amount' => '10.00',
        'other_costs_amount' => '1.01',
        'status' => 'received',
    ]);
    $lineId = DB::table('purchase_lines')->insertGetId([
        'purchase_id' => $purchaseId,
        'product_id' => $rawId,
        'invoiced_weight_kg' => '10.000',
        'line_amount' => '100.00',
        'received_weight_kg' => '10.000',
        'allocated_freight' => '10.00',
        'allocated_other_costs' => '1.01',
    ]);
    legacyBatch("purchase_line-{$lineId}", 'purchase_line', $lineId, $rawId, '11.1010', '111.01', '85.51');

    return [$purchaseId, $lineId];
}

it('reconciles a single-line purchase and leaves odd legacy rows alone [S6 edge cases]', function () {
    $migration = moneyMigration();
    $migration->down();

    $supplierId = Supplier::factory()->create()->id;
    $rawId = createRawMaterial()->id;
    $userId = User::factory()->create()->id;

    // Compra de un solo renglón: el residuo cae en esa línea y cuadra con el flete redondeado.
    $purchaseId = DB::table('purchases')->insertGetId([
        'supplier_id' => $supplierId, 'supplier_invoice_number' => 'F-3', 'supplier_invoice_date' => now()->toDateString(),
        'currency' => 'VES', 'exchange_rate' => '85.5050', 'freight_amount' => '10.0049', 'other_costs_amount' => '0.0000', 'status' => 'received',
    ]);
    $lineId = DB::table('purchase_lines')->insertGetId([
        'purchase_id' => $purchaseId, 'product_id' => $rawId, 'invoiced_weight_kg' => '5.000', 'line_amount' => '20.0000',
        'received_weight_kg' => '5.000', 'allocated_freight' => '10.0049', 'allocated_other_costs' => null,
    ]);

    // Lote sin línea de origen y lote con total NULL: no fallan.
    $orphanId = legacyBatch('orphan-1', 'purchase_line', 999999, $rawId, '1.0000', '12.3456', '85.5050');
    $nullTotalId = legacyBatch('null-total', 'purchase_line', $lineId, $rawId, '1.0000', '1.0000', '85.5050');
    DB::statement('ALTER TABLE batches ALTER COLUMN total_cost DROP NOT NULL');
    DB::table('batches')->where('id', $nullTotalId)->update(['total_cost' => null]);

    // Orden cerrada sin costos asignados (NULL) y orden con residuo negativo.
    $inputId = legacyBatch('input-2', 'purchase_line', null, $rawId, '1.0000', '0.0100', '85.5050');
    $templateId = DB::table('butchery_templates')->insertGetId([
        'raw_material_product_id' => $rawId, 'name' => 'Plantilla 2', 'expected_shrinkage_percent' => '5.00', 'created_by' => $userId,
    ]);
    $orderIds = [];
    foreach (['ORD-N', 'ORD-R'] as $code) {
        $orderIds[$code] = DB::table('butchery_orders')->insertGetId([
            'code' => $code, 'batch_id' => $inputId, 'template_id' => $templateId, 'input_weight_kg' => '2.000',
            'expected_shrinkage_percent' => '5.00', 'butcher_id' => $userId, 'status' => 'closed',
        ]);
    }
    $line = fn (int $orderId, string $weight, ?string $cost) => DB::table('butchery_order_lines')->insertGetId([
        'order_id' => $orderId, 'cut_product_id' => createCut('Corte '.uniqid())->id, 'expected_yield_percent' => '10.00',
        'expected_weight_kg' => $weight, 'actual_weight_kg' => $weight, 'reference_price_per_kg' => '1.0000', 'allocated_cost' => $cost,
    ]);
    $nullLine = $line($orderIds['ORD-N'], '1.000', null);
    $negA = $line($orderIds['ORD-R'], '2.000', '0.0050');
    $negB = $line($orderIds['ORD-R'], '1.000', '0.0050');

    $migration->up();

    expect(DB::table('purchase_lines')->where('id', $lineId)->value('allocated_freight'))->toBe('10.00')
        ->and(DB::table('purchases')->where('id', $purchaseId)->value('freight_amount'))->toBe('10.00')
        ->and(DB::table('batches')->where('id', $orphanId)->value('total_cost'))->toBe('12.35')
        ->and(DB::table('batches')->where('id', $nullTotalId)->value('total_cost'))->toBeNull()
        ->and(DB::table('purchase_lines')->where('id', $lineId)->value('allocated_other_costs'))->toBeNull()
        ->and(DB::table('butchery_order_lines')->where('id', $nullLine)->value('allocated_cost'))->toBeNull();

    // Residuo: 0.0050 + 0.0050 = 0.0100 exacto; cada corte redondea a 0.01 (suma 0.02) y el de mayor valor cede 0.01.
    expect(DB::table('butchery_order_lines')->whereIn('id', [$negA, $negB])->orderBy('id')->pluck('allocated_cost')->all())->toBe(['0.00', '0.01'])
        ->and((string) DB::table('butchery_order_lines')->whereIn('id', [$negA, $negB])->sum('allocated_cost'))->toBe('0.01');
});
