<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\ConsumeStockFefoAction;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\MovementType;
use App\Domain\Inventory\Queries\StockBalance;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// BR-14: salida FEFO con costo exacto por lote (C9). BR-17: faltante según
// `inventory.allow_sale_without_stock`. DEC-02 (sin fecha al final), DEC-03 (vencidos saltados).

function fefoLot(int $productId, string $kg, string $totalCost, string $unitCost, ?string $expiresOn = null, ?CarbonImmutable $receivedAt = null): Batch
{
    $batch = Batch::factory()->create([
        'product_id' => $productId,
        'total_cost' => $totalCost,
        'unit_cost' => $unitCost,
        'expires_at' => $expiresOn,
        'received_at' => $receivedAt ?? now(),
    ]);

    InventoryMovement::create([
        'batch_id' => $batch->id,
        'type' => MovementType::PurchaseIn->value,
        'quantity' => $kg,
        'unit_cost' => $unitCost,
        'total_cost' => $totalCost,
        'user_id' => User::factory()->create()->id,
        'occurred_at' => now(),
    ]);

    return $batch;
}

function day(int $offset): string
{
    return CarbonImmutable::now('UTC')->startOfDay()->addDays($offset)->toDateTimeString();
}

function consumeFefo(int $productId, string $kg, ?int $userId = null, ?string $reason = null): object
{
    return (new ConsumeStockFefoAction)->execute(
        productId: $productId,
        kg: $kg,
        type: MovementType::SaleOut,
        referenceType: 'sale',
        referenceId: 77,
        userId: $userId ?? User::factory()->create()->id,
        reason: $reason,
    );
}

function exits(): array
{
    return InventoryMovement::where('type', MovementType::SaleOut->value)->orderBy('id')->get()
        ->map(fn ($m) => [$m->batch_id, $m->quantity, $m->unit_cost, $m->total_cost])->all();
}

function allowSaleWithoutStock(string $value): void
{
    Setting::updateOrCreate(['key' => 'inventory.allow_sale_without_stock'], ['value' => $value]);
}

it('consumes the lot that expires first and writes the exact exit row [BR-14]', function () {
    $product = createRawMaterial();
    $later = fefoLot($product->id, '10.000', '20.00', '2.0000', day(10));
    $sooner = fefoLot($product->id, '10.000', '30.00', '3.0000', day(2));
    $user = User::factory()->create();

    $result = consumeFefo($product->id, '4.000', $user->id, 'venta mostrador');

    $movement = InventoryMovement::where('type', 'sale_out')->sole();
    expect(InventoryMovement::where('type', 'sale_out')->count())->toBe(1)
        ->and($movement->batch_id)->toBe($sooner->id)
        ->and($movement->quantity)->toBe('-4.000')
        ->and($movement->unit_cost)->toBe('3.0000')
        ->and($movement->total_cost)->toBe('-12.00')
        ->and($movement->user_id)->toBe($user->id)
        ->and($movement->reference_type)->toBe('sale')
        ->and($movement->reference_id)->toBe(77)
        ->and($movement->reason)->toBe('venta mostrador')
        ->and($movement->occurred_at)->not->toBeNull()
        ->and((new StockBalance)->forBatch($sooner->id))->toBe('6.000')
        ->and((new StockBalance)->forBatch($later->id))->toBe('10.000')
        ->and((new StockBalance)->valueForBatch($sooner->id)->toInternalString())->toBe('18.00')
        ->and($result->allocations)->toHaveCount(1)
        ->and($result->allocations[0]->batch->id)->toBe($sooner->id)
        ->and($result->allocations[0]->kg)->toBe('4.000')
        ->and($result->allocations[0]->cost->toInternalString())->toBe('12.00')
        ->and($result->totalCost)->toBeInstanceOf(Money::class)
        ->and($result->totalCost->toInternalString())->toBe('12.00');
});

it('puts lots without expiry date last, oldest received first, then by id [DEC-02][BR-14]', function () {
    $product = createRawMaterial();
    $noDateNew = fefoLot($product->id, '1.000', '1.00', '1.0000', null, CarbonImmutable::now()->subDay());
    $noDateOld = fefoLot($product->id, '1.000', '1.00', '1.0000', null, CarbonImmutable::now()->subDays(5));
    $dated = fefoLot($product->id, '1.000', '1.00', '1.0000', day(30));
    $tieA = fefoLot($product->id, '1.000', '1.00', '1.0000', day(31), CarbonImmutable::now()->subDay());
    $tieB = fefoLot($product->id, '1.000', '1.00', '1.0000', day(31), CarbonImmutable::now()->subDays(3));

    consumeFefo($product->id, '5.000');

    expect(array_column(exits(), 0))->toBe([$dated->id, $tieB->id, $tieA->id, $noDateOld->id, $noDateNew->id]);
});

it('breaks ties of same expiry and same received_at by id [DEC-02]', function () {
    $product = createRawMaterial();
    $at = CarbonImmutable::now()->subDay();
    $first = fefoLot($product->id, '1.000', '1.00', '1.0000', day(5), $at);
    $second = fefoLot($product->id, '1.000', '1.00', '1.0000', day(5), $at);

    consumeFefo($product->id, '2.000');

    expect(array_column(exits(), 0))->toBe([$first->id, $second->id]);
});

it('skips expired lots and treats a lot expiring today as still sellable [DEC-03][BR-14]', function () {
    $product = createRawMaterial();
    $expired = fefoLot($product->id, '10.000', '10.00', '1.0000', day(-1));
    $today = fefoLot($product->id, '10.000', '10.00', '1.0000', day(0));
    $future = fefoLot($product->id, '10.000', '10.00', '1.0000', day(5));

    consumeFefo($product->id, '12.000');

    expect(array_column(exits(), 0))->toBe([$today->id, $future->id])
        ->and((new StockBalance)->forBatch($expired->id))->toBe('10.000')
        ->and((new StockBalance)->forBatch($today->id))->toBe('0.000')
        ->and((new StockBalance)->forBatch($future->id))->toBe('8.000');
});

it('consumes across two lots taking each lot cost and leaving the first at zero [BR-14][C9]', function () {
    $product = createRawMaterial();
    $a = fefoLot($product->id, '5.000', '10.00', '2.0000', day(1));
    $b = fefoLot($product->id, '10.000', '30.00', '3.0000', day(2));

    $result = consumeFefo($product->id, '8.000');

    expect(exits())->toBe([
        [$a->id, '-5.000', '2.0000', '-10.00'],
        [$b->id, '-3.000', '3.0000', '-9.00'],
    ])
        ->and((new StockBalance)->forBatch($a->id))->toBe('0.000')
        ->and((new StockBalance)->forBatch($b->id))->toBe('7.000')
        ->and((new StockBalance)->valueForBatch($a->id)->toInternalString())->toBe('0.00')
        ->and((new StockBalance)->valueForBatch($b->id)->toInternalString())->toBe('21.00')
        ->and(array_map(fn ($x) => [$x->batch->id, $x->kg, $x->cost->toInternalString()], $result->allocations))
        ->toBe([[$a->id, '5.000', '10.00'], [$b->id, '3.000', '9.00']])
        ->and($result->totalCost->toInternalString())->toBe('19.00');
});

it('costs a whole 200 kg lot at its total_cost 190.01, not 190.02 [C9][BR-14]', function () {
    $product = createRawMaterial();
    $lot = fefoLot($product->id, '200.000', '190.01', '0.9501');

    $result = consumeFefo($product->id, '200.000');

    expect(exits())->toBe([[$lot->id, '-200.000', '0.9501', '-190.01']])
        ->and($result->totalCost->toInternalString())->toBe('190.01')
        ->and((new StockBalance)->forBatch($lot->id))->toBe('0.000')
        ->and((new StockBalance)->valueForBatch($lot->id)->toInternalString())->toBe('0.00');
});

it('makes the sum of the exit costs equal the lot total_cost when consumed in parts [C9]', function () {
    $product = createRawMaterial();
    $lot = fefoLot($product->id, '950.000', '902.60', '0.9501');

    foreach (['300.000', '333.333', '316.667'] as $part) {
        consumeFefo($product->id, $part);
    }

    $sum = Money::zero();
    foreach (InventoryMovement::where('type', 'sale_out')->get() as $movement) {
        $sum = $sum->add(Money::fromString($movement->total_cost));
    }

    expect(InventoryMovement::where('type', 'sale_out')->count())->toBe(3)
        ->and($sum->toInternalString())->toBe('-902.60')
        ->and((new StockBalance)->forBatch($lot->id))->toBe('0.000')
        ->and((new StockBalance)->valueForBatch($lot->id)->toInternalString())->toBe('0.00');

    // Las dos primeras salidas se costean kg × unit_cost al centavo; la última toma el residuo.
    $costs = InventoryMovement::where('type', 'sale_out')->orderBy('id')->pluck('total_cost')->all();
    expect($costs[2])->toBe('-300.87')
        ->and($costs[0])->toBe('-285.03')
        ->and($costs[1])->toBe('-316.70');
});

it('rejects a request above the available balance and writes nothing in block mode (default) [BR-17]', function () {
    $product = createRawMaterial();
    $a = fefoLot($product->id, '5.000', '10.00', '2.0000', day(1));
    $b = fefoLot($product->id, '3.000', '9.00', '3.0000', day(2));
    $before = InventoryMovement::count();

    expect(fn () => consumeFefo($product->id, '8.001'))
        ->toThrow(RuntimeException::class, 'Stock insuficiente');

    expect(InventoryMovement::count())->toBe($before)
        ->and((new StockBalance)->forBatch($a->id))->toBe('5.000')
        ->and((new StockBalance)->forBatch($b->id))->toBe('3.000');
});

it('blocks too when the setting is explicitly false [BR-17]', function () {
    $product = createRawMaterial();
    fefoLot($product->id, '1.000', '1.00', '1.0000');
    allowSaleWithoutStock('false');

    expect(fn () => consumeFefo($product->id, '2.000'))->toThrow(RuntimeException::class);
    expect(InventoryMovement::where('type', 'sale_out')->count())->toBe(0);
});

it('does not count expired lots as available stock in block mode [DEC-03][BR-17]', function () {
    $product = createRawMaterial();
    fefoLot($product->id, '100.000', '100.00', '1.0000', day(-3));
    $good = fefoLot($product->id, '2.000', '2.00', '1.0000', day(3));

    expect(fn () => consumeFefo($product->id, '3.000'))->toThrow(RuntimeException::class);

    expect(InventoryMovement::where('type', 'sale_out')->count())->toBe(0)
        ->and((new StockBalance)->forBatch($good->id))->toBe('2.000');
});

it('takes the excess from the last FEFO lot leaving it negative when the setting is true [BR-17][DEC-03]', function () {
    $product = createRawMaterial();
    $a = fefoLot($product->id, '5.000', '10.00', '2.0000', day(1));
    $b = fefoLot($product->id, '3.000', '9.00', '3.0000', day(2));
    allowSaleWithoutStock('true');
    $user = User::factory()->create();

    $result = consumeFefo($product->id, '10.000', $user->id);

    expect(exits())->toBe([
        [$a->id, '-5.000', '2.0000', '-10.00'],
        [$b->id, '-3.000', '3.0000', '-9.00'],
        [$b->id, '-2.000', '3.0000', '-6.00'],
    ])
        ->and(InventoryMovement::where('type', 'sale_out')->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and((new StockBalance)->forBatch($a->id))->toBe('0.000')
        ->and((new StockBalance)->forBatch($b->id))->toBe('-2.000')
        ->and((new StockBalance)->valueForBatch($b->id)->toInternalString())->toBe('-6.00')
        ->and((new StockBalance)->forProduct($product->id))->toBe('-2.000')
        ->and(array_map(fn ($x) => [$x->batch->id, $x->kg, $x->cost->toInternalString()], $result->allocations))
        ->toBe([[$a->id, '5.000', '10.00'], [$b->id, '3.000', '9.00'], [$b->id, '2.000', '6.00']])
        ->and($result->totalCost->toInternalString())->toBe('25.00');
});

it('consumes normally and leaves no negative balance when stock suffices with the setting true [BR-17]', function () {
    $product = createRawMaterial();
    $a = fefoLot($product->id, '5.000', '10.00', '2.0000');
    allowSaleWithoutStock('true');

    consumeFefo($product->id, '5.000');

    expect(exits())->toBe([[$a->id, '-5.000', '2.0000', '-10.00']])
        ->and((new StockBalance)->forBatch($a->id))->toBe('0.000');
});

it('does not take the excess from an expired lot in allow mode [DEC-03]', function () {
    $product = createRawMaterial();
    $expired = fefoLot($product->id, '5.000', '5.00', '1.0000', day(-1));
    $good = fefoLot($product->id, '1.000', '4.00', '4.0000', day(9));
    allowSaleWithoutStock('true');

    consumeFefo($product->id, '2.000');

    expect(exits())->toBe([
        [$good->id, '-1.000', '4.0000', '-4.00'],
        [$good->id, '-1.000', '4.0000', '-4.00'],
    ])->and((new StockBalance)->forBatch($expired->id))->toBe('5.000');
});

it('rejects a product without any lot in both modes and writes nothing [BR-17][DEC-03]', function () {
    $product = createRawMaterial();

    expect(fn () => consumeFefo($product->id, '1.000'))->toThrow(RuntimeException::class);

    allowSaleWithoutStock('true');
    expect(fn () => consumeFefo($product->id, '1.000'))->toThrow(RuntimeException::class);

    expect(InventoryMovement::count())->toBe(0);
});

it('rejects when every lot is expired, empty or already negative, in both modes [DEC-03][BR-17]', function () {
    $product = createRawMaterial();
    fefoLot($product->id, '5.000', '5.00', '1.0000', day(-2));
    $empty = fefoLot($product->id, '1.000', '1.00', '1.0000');
    InventoryMovement::create([
        'batch_id' => $empty->id, 'type' => 'sale_out', 'quantity' => '-2.000', 'unit_cost' => '1.0000',
        'total_cost' => '-2.00', 'user_id' => User::factory()->create()->id, 'occurred_at' => now(),
    ]);
    $before = InventoryMovement::count();

    expect(fn () => consumeFefo($product->id, '1.000'))->toThrow(RuntimeException::class);
    allowSaleWithoutStock('true');
    expect(fn () => consumeFefo($product->id, '1.000'))->toThrow(RuntimeException::class);

    expect(InventoryMovement::count())->toBe($before);
});

it('ignores lots of other products [BR-14]', function () {
    $product = createRawMaterial('Pollo');
    $other = createRawMaterial('Res');
    fefoLot($other->id, '50.000', '50.00', '1.0000', day(1));
    $mine = fefoLot($product->id, '2.000', '4.00', '2.0000', day(9));

    consumeFefo($product->id, '1.000');

    expect(array_column(exits(), 0))->toBe([$mine->id])
        ->and((new StockBalance)->forProduct($other->id))->toBe('50.000');
});

it('rejects invalid weights without writing [BR-01]', function (string $kg) {
    $product = createRawMaterial();
    fefoLot($product->id, '5.000', '5.00', '1.0000');

    expect(fn () => consumeFefo($product->id, $kg))->toThrow(InvalidArgumentException::class);

    expect(InventoryMovement::where('type', 'sale_out')->count())->toBe(0);
})->with(['0.000', '-1.000', '1.0005', 'abc']);

it('does not write audit logs for sales [C15]', function () {
    $product = createRawMaterial();
    fefoLot($product->id, '5.000', '5.00', '1.0000');

    consumeFefo($product->id, '1.000');

    expect(DB::table('audit_logs')->count())->toBe(0);
});
