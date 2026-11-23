@php($company = app(\App\Domain\Settings\CompanySettings::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $company->name() }}</title>
        @if ($company->faviconUrl())
            <link rel="icon" href="{{ $company->faviconUrl() }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        {{-- C6 / docs/design.md §2.4: la marca de settings sobrescribe los valores por defecto de app.css. --}}
        <style>html:root { --brand: {{ $company->brandColor() }}; --accent: {{ $company->accentColor() }}; }</style>
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
