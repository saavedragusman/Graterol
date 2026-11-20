<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\RegisterExchangeRateAction;
use App\Domain\Settings\Models\ExchangeRate;
use App\Jobs\FetchBcvRateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// BR-02: la tasa BCV es diaria, con carga automática y respaldo manual.

it('registers a manual rate [BR-02]', function () {
    $rate = (new RegisterExchangeRateAction)->execute('85.5000', 'manual');

    expect($rate->source)->toBe('manual')
        ->and($rate->rate)->toBe('85.50');
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

it('queues the automatic BCV fetch job instead of calling the API inline [BR-02]', function () {
    Queue::fake();

    $this->artisan('bcv:fetch')->assertExitCode(0);

    Queue::assertPushed(FetchBcvRateJob::class);
});

it('stores the rate with 2 decimals, rounding half-up, for any source [BR-02]', function (string $input, string $expected) {
    expect((new RegisterExchangeRateAction)->execute($input, 'automatic')->rate)->toBe($expected);
})->with([
    ['918.7662', '918.77'],
    ['918.764', '918.76'],
    ['918.765', '918.77'],
    ['85', '85.00'],
]);

it('rejects a rate that rounds to zero and stores nothing [BR-02]', function () {
    expect(fn () => (new RegisterExchangeRateAction)->execute('0.004', 'automatic'))->toThrow(InvalidArgumentException::class);
    expect(ExchangeRate::count())->toBe(0);
});
