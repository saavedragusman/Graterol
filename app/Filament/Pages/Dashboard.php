<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\HomeGreeting;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Inicio de la oficina (d-dashboard.html). Por ahora solo saludo y fecha: las
 * tarjetas con datos son del hito M6 (BR-06, BR-11, BR-21, BR-28) y no se inventan aquí.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Inicio';

    protected static ?string $navigationLabel = 'Inicio';

    public function getHeading(): string|Htmlable
    {
        return HomeGreeting::greeting(fullName: auth()->user()?->name);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return HomeGreeting::dateLine();
    }

    /** Sin las tarjetas de cuenta e información de Filament. */
    public function getWidgets(): array
    {
        return [];
    }
}
