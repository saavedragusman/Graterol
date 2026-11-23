<?php

use App\Domain\Settings\Models\ExchangeRate;
use App\Jobs\FetchBcvRateJob;
use App\Support\DisplayTimezone;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// S3/BR-02: carga diaria de la tasa BCV a las 8:00 a. m. de Caracas; si la fuente falla
// reintenta cada hora hasta las 12:00, solo mientras no haya una tasa automática de hoy.
// Corre en el proceso del programador (sin worker de cola); el job registra sus errores (C7).
Schedule::call(fn () => FetchBcvRateJob::dispatchSync())
    ->name('bcv-rate-fetch')
    ->cron('0 8-12 * * *')
    ->timezone(DisplayTimezone::CARACAS)
    ->when(fn (): bool => ! ExchangeRate::hasAutomaticToday())
    ->withoutOverlapping();
