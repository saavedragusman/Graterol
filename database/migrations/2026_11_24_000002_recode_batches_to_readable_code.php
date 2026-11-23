<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BR-14/DEC-10 B/C18: re-codifica los lotes existentes (`purchase_line-12`,
 * `butchery_order_line-5`...) a `L-0001`, `L-0002`... por orden de id y
 * guarda el código anterior en `legacy_code`.
 *
 * Solo toca `batches`: el kardex referencia `batch_id`, no el código, y el
 * trigger de `inventory_movements` (C10) rechaza cualquier UPDATE/DELETE.
 *
 * - Idempotente: un lote cuyo código ya es `L-<dígitos>` conserva su código y su
 *   `legacy_code` queda en NULL; ejecutarla otra vez no cambia nada.
 * - Sin choques de unicidad: los lotes viejos reciben números por encima del
 *   mayor `L-<dígitos>` existente (0 si no hay ninguno), y los códigos viejos
 *   nunca tienen la forma `L-<dígitos>`, así que ningún valor intermedio repite.
 * - Comparte el candado de numeración de `BatchCodeGenerator`.
 */
return new class extends Migration
{
    /** Mismo candado que `App\Domain\Inventory\BatchCodeGenerator`. */
    private const CODE_LOCK = 4160002;

    public function up(): void
    {
        if (! Schema::hasColumn('batches', 'legacy_code')) {
            Schema::table('batches', function ($table) {
                $table->string('legacy_code', 30)->nullable()->after('code');
            });
        }

        DB::transaction(function () {
            DB::select('select pg_advisory_xact_lock(?)', [self::CODE_LOCK]);

            // Un solo UPDATE: calcula todos los códigos nuevos antes de escribir.
            DB::statement(<<<'SQL'
                WITH base AS (
                    SELECT COALESCE(MAX(CAST(substring(code from 3) AS numeric)), 0) AS last_number
                    FROM batches
                    WHERE code ~ '^L-[0-9]+$'
                ),
                ranked AS (
                    SELECT id, code, ROW_NUMBER() OVER (ORDER BY id) AS position
                    FROM batches
                    WHERE code !~ '^L-[0-9]+$'
                )
                UPDATE batches
                SET legacy_code = ranked.code,
                    code = 'L-' || CASE
                        WHEN length(CAST(base.last_number + ranked.position AS text)) >= 4
                            THEN CAST(base.last_number + ranked.position AS text)
                        ELSE lpad(CAST(base.last_number + ranked.position AS text), 4, '0')
                    END
                FROM ranked, base
                WHERE batches.id = ranked.id
            SQL);
        });
    }

    /**
     * Restaura el código anterior de los lotes re-codificados (los que tienen
     * `legacy_code`) y quita la columna. Los lotes creados después con `L-####`
     * conservan su código.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('batches', 'legacy_code')) {
            return;
        }

        DB::transaction(function () {
            DB::statement('UPDATE batches SET code = legacy_code WHERE legacy_code IS NOT NULL');
        });

        Schema::table('batches', function ($table) {
            $table->dropColumn('legacy_code');
        });
    }
};
