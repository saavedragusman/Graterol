<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\ExchangeRate;
use App\Jobs\FetchBcvRateJob;
use App\Support\DisplayTimezone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// S3 (BR-02, C7, C13): carga diaria programada de la tasa BCV.

function bcvEvent(): ?Event
{
    // Fuerza la carga de routes/console.php.
    test()->artisan('list');

    return collect(app(Schedule::class)->events())
        ->first(fn (Event $e): bool => $e->description === 'bcv-rate-fetch');
}

afterEach(fn () => Carbon::setTestNow());

it('schedules the fetch at 08:00 Caracas, hourly until 12:00, without overlapping [S3]', function () {
    $event = bcvEvent();

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8-12 * * *')
        ->and($event->timezone)->toBe(DisplayTimezone::CARACAS)
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('only runs the retries while there is no automatic rate today in Caracas [S3]', function () {
    $event = bcvEvent();

    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', DisplayTimezone::CARACAS));
    expect($event->filtersPass(app()))->toBeTrue();

    (new RegisterExchangeRateAction)->execute('90', 'automatic');
    expect($event->filtersPass(app()))->toBeFalse();
});

it('detects an automatic rate effective today (Caracas day) [S3]', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-20 09:00:00', DisplayTimezone::CARACAS));

    expect(ExchangeRate::hasAutomaticToday())->toBeFalse();

    // Manual de hoy no cuenta.
    (new RegisterExchangeRateAction)->execute('90', 'manual');
    expect(ExchangeRate::hasAutomaticToday())->toBeFalse();

    // Automática de ayer en Caracas (23:30 de Caracas = 03:30 UTC de hoy) no cuenta.
    (new RegisterExchangeRateAction)->execute('91', 'automatic', effectiveAt: Carbon::parse('2026-11-19 23:30:00', DisplayTimezone::CARACAS)->utc());
    expect(ExchangeRate::hasAutomaticToday())->toBeFalse();

    // Automática de hoy en Caracas (00:30 de Caracas = 04:30 UTC) cuenta.
    (new RegisterExchangeRateAction)->execute('92', 'automatic', effectiveAt: Carbon::parse('2026-11-20 00:30:00', DisplayTimezone::CARACAS)->utc());
    expect(ExchangeRate::hasAutomaticToday())->toBeTrue();
});

it('stores the rate when the job runs in the scheduler process [S3]', function () {
    Http::fake(['*' => Http::response(['promedio' => 918.7662])]);

    FetchBcvRateJob::dispatchSync();

    expect(ExchangeRate::count())->toBe(1)
        ->and(ExchangeRate::first()->source)->toBe('automatic')
        ->and(ExchangeRate::first()->rate)->toBe('918.7700');
});

it('logs instead of throwing when the source fails [S3][C7]', function () {
    Http::fake(['*' => Http::response('error', 500)]);
    Log::spy();

    FetchBcvRateJob::dispatchSync();

    expect(ExchangeRate::count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});
