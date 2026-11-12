<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// BR-02: la tasa BCV es diaria, con carga automática y respaldo manual.

it('registers a manual rate [BR-02]', function () {
    $rate = (new RegisterExchangeRateAction)->execute('85.5000', 'manual');

    expect($rate->source)->toBe('manual')
        ->and($rate->rate)->toBe('85.5000');
});

it('returns the most recently effective rate as current [BR-02]', function () {
    (new RegisterExchangeRateAction)->execute('80.0000', 'automatic', effectiveAt: Carbon::yesterday());
    $today = (new RegisterExchangeRateAction)->execute('85.5000', 'manual', effectiveAt: Carbon::now());

    expect(ExchangeRate::current()?->id)->toBe($today->id);
});

it('never updates or deletes a previous rate when a new one is registered [BR-02]', function () {
    (new RegisterExchangeRateAction)->execute('80.0000', 'automatic', effectiveAt: Carbon::yesterday());
    (new RegisterExchangeRateAction)->execute('85.5000', 'manual', effectiveAt: Carbon::now());

    expect(ExchangeRate::count())->toBe(2);
});

it('runs bcv:fetch as a stub without calling any API [BR-02]', function () {
    $this->artisan('bcv:fetch')->assertExitCode(0);
});
