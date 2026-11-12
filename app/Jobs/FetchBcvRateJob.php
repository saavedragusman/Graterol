<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BR-02/C7: carga automática de la tasa BCV. Corre encolada y fuera del
 * núcleo: una caída de la fuente externa nunca debe parar la caja.
 */
class FetchBcvRateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RegisterExchangeRateAction $registerExchangeRate): void
    {
        try {
            $response = Http::timeout(10)->get(config('services.bcv.rate_url'));
            $response->throw();

            $rate = $response->json('promedio') ?? $response->json('venta') ?? $response->json('compra');

            if ($rate === null) {
                throw new \RuntimeException('La fuente de la tasa BCV no devolvió un valor usable.');
            }

            $registerExchangeRate->execute((string) $rate, 'automatic');
        } catch (Throwable $e) {
            Log::warning('No se pudo cargar la tasa BCV automáticamente: '.$e->getMessage());
        }
    }
}
