<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * C10/C11: hasta ahora `batches.code` (`purchase_line-12`, `butchery_order_line-5`)
 * impedía en la base crear dos lotes del mismo origen. Con el código legible
 * `L-####` (DEC-10 B) esa garantía se pierde, así que pasa a un índice único
 * propio. Los orígenes sin `source_id` (NULL) no chocan entre sí en PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function ($table) {
            $table->unique(['source_type', 'source_id'], 'batches_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function ($table) {
            $table->dropUnique('batches_source_unique');
        });
    }
};
