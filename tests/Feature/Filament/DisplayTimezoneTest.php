<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Livewire\Component;

// S11/C13: se guarda en UTC y la oficina muestra en America/Caracas (UTC-4, sin horario de verano).

it('guarda en UTC y muestra la zona de Caracas en Filament', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(FilamentTimezone::get())->toBe('America/Caracas');
});

it('muestra en hora de Caracas un instante guardado en UTC', function () {
    $stored = CarbonImmutable::parse('2026-11-20 03:30:00', 'UTC');

    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $entry = TextEntry::make('at')->state($stored)->dateTime('d/m/Y H:i');
    Schema::make($livewire)->components([$entry])->getComponents();

    expect($entry->formatState($stored))->toBe('19/11/2026 23:30');
});
