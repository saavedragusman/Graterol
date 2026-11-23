<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Inventory\Models\Batch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-14/DEC-10 B: código de lote legible `L-0001`, `L-0002`... (mínimo 4
 * dígitos; los números más largos simplemente crecen). El siguiente es el
 * mayor número de los códigos `L-<dígitos>` más uno.
 *
 * Debe llamarse dentro de la transacción que crea el lote: el candado
 * consultivo dura hasta su fin, así dos creaciones simultáneas nunca
 * comparten código y, si la creación falla, no queda un hueco.
 */
class BatchCodeGenerator
{
    /** Clave propia del candado de numeración de lotes (distinta de la de conteos). */
    private const CODE_LOCK = 4160002;

    public function next(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('El código de lote se genera dentro de la transacción que crea el lote.');
        }

        DB::select('select pg_advisory_xact_lock(?)', [self::CODE_LOCK]);

        $last = (int) Batch::query()
            ->whereRaw("code ~ '^L-[0-9]+$'")
            ->selectRaw('max(cast(substring(code from 3) as numeric)) as last_number')
            ->value('last_number');

        return sprintf('L-%04d', $last + 1);
    }
}
