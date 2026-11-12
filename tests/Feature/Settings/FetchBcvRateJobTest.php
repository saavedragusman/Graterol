<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\ExchangeRate;
use App\Jobs\FetchBcvRateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// BR-02/C7: carga automática de la tasa BCV, encolada y fuera del núcleo.

it('registers an automatic rate from the configured BCV source [BR-02]', function () {
    Http::fake([
        config('services.bcv.rate_url') => Http::response([
            'fuente' => 'oficial',
            'nombre' => 'Oficial',
            'compra' => 85.5,
            'venta' => 85.5,
            'promedio' => 85.5,
            'fechaActualizacion' => now()->toISOString(),
        ], 200),
    ]);

    app(FetchBcvRateJob::class)->handle(app(RegisterExchangeRateAction::class));

    $rate = ExchangeRate::current();

    expect($rate)->not->toBeNull()
        ->and($rate->source)->toBe('automatic')
        ->and((float) $rate->rate)->toBe(85.5);
});

it('never lets a failed BCV fetch throw: it logs and keeps going [BR-02][C7]', function () {
    Http::fake([
        config('services.bcv.rate_url') => Http::response('service unavailable', 503),
    ]);
    Log::shouldReceive('warning')->once();

    app(FetchBcvRateJob::class)->handle(app(RegisterExchangeRateAction::class));

    expect(ExchangeRate::count())->toBe(0);
});
