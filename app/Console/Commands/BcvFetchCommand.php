<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FetchBcvRateJob;
use Illuminate\Console\Command;

/**
 * BR-02/C7: encola la carga automática de la tasa BCV. El fetch real
 * corre en el worker de la cola, nunca de forma síncrona en este comando.
 */
class BcvFetchCommand extends Command
{
    protected $signature = 'bcv:fetch';

    protected $description = 'Encola la carga automática de la tasa BCV del día.';

    public function handle(): int
    {
        FetchBcvRateJob::dispatch();

        $this->info('Tasa BCV encolada para actualizarse automáticamente.');

        return self::SUCCESS;
    }
}
