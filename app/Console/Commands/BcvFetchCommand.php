<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * BR-02: carga automática de la tasa BCV. Pendiente de implementar contra
 * la fuente real; por ahora es un stub que no llama a ninguna API.
 */
class BcvFetchCommand extends Command
{
    protected $signature = 'bcv:fetch';

    protected $description = 'Carga la tasa BCV del día (stub: aún no conectado a la fuente real).';

    public function handle(): int
    {
        $this->info('bcv:fetch es un stub: todavía no está conectado a la fuente real del BCV. Registre la tasa manualmente mientras tanto.');

        return self::SUCCESS;
    }
}
