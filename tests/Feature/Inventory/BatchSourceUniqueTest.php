<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Batch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// C10/C11: con el código legible L-#### el código ya no delata un origen repetido
// (antes `purchase_line-9` único); la unicidad pasa a (source_type, source_id).

function sourcedBatch(string $code, string $type, ?int $sourceId): Batch
{
    return Batch::create(['code' => $code, 'source_type' => $type, 'source_id' => $sourceId, 'unit' => 'kg', 'unit_cost' => '1.0000', 'received_at' => now()]);
}

it('rejects a second batch for the same source [C10]', function () {
    sourcedBatch('L-0001', 'purchase_line', 9);

    expect(fn () => sourcedBatch('L-0002', 'purchase_line', 9))->toThrow(QueryException::class);
});

it('allows the same source id under another source type, and batches without source id', function () {
    sourcedBatch('L-0001', 'purchase_line', 9);
    sourcedBatch('L-0002', 'butchery_order_line', 9);
    sourcedBatch('L-0003', 'purchase', null);
    sourcedBatch('L-0004', 'purchase', null);

    expect(Batch::count())->toBe(4);
});
