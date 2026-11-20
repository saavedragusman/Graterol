<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\CompanySettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Vista raíz que se carga en la primera visita.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determina la versión actual de los assets.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props compartidas por defecto en cada página Inertia.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $company = app(CompanySettings::class);

        return [
            ...parent::share($request),
            // C6: identidad de la empresa desde settings; los colores van en el layout.
            'company' => [
                'name' => $company->name(),
                'logoUrl' => $company->logoUrl(),
            ],
        ];
    }
}
