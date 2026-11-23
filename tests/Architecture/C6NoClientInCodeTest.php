<?php

// C6: nada del cliente en el código. Verifica: grep -ri graterol app/ database/migrations → 0.

function filesMentioningClient(string $directory): array
{
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (stripos((string) file_get_contents($file->getPathname()), 'graterol') !== false) {
            $offenders[] = $file->getPathname();
        }
    }

    return $offenders;
}

it('keeps the pilot client out of app/ and migrations [C6]', function () {
    $offenders = [
        ...filesMentioningClient(base_path('app')),
        ...filesMentioningClient(base_path('database/migrations')),
    ];

    expect($offenders)->toBe([]);
});

it('keeps the pilot client out of css and views [C6]', function () {
    $offenders = [
        ...filesMentioningClient(base_path('resources/css')),
        ...filesMentioningClient(base_path('resources/views')),
    ];

    expect($offenders)->toBe([]);
});
