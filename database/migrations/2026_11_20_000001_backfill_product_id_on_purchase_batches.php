<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 2026_11_17_000006 agregó `batches.product_id` sin completar los lotes que
     * ya existían. Los lotes nacidos de una recepción (M2) quedaron en NULL y el
     * despiece (BR-09) no los reconocía como materia prima. Aquí se copia el
     * producto desde su línea de compra; solo toca lotes de compra sin producto.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE batches
            SET product_id = purchase_lines.product_id
            FROM purchase_lines
            WHERE batches.source_type = 'purchase_line'
              AND batches.source_id = purchase_lines.id
              AND batches.product_id IS NULL
        SQL);
    }

    /**
     * Sin reversión: no se puede distinguir qué lotes completó esta migración, y
     * dejarlos en NULL otra vez solo volvería a esconderlos del despiece.
     */
    public function down(): void
    {
        //
    }
};
