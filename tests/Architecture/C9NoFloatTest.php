<?php

// C9: peso numeric(12,3), dinero numeric(18,4); float/double prohibidos en
// columnas, casts y cálculos de peso o dinero en migraciones y app/Domain.

function filesWithForbiddenFloatTokens(string $directory): array
{
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (preg_match('/\b(float|double)\b/i', $contents)) {
            $offenders[] = $file->getPathname();
        }
    }

    return $offenders;
}

it('has no float or double tokens in migrations [C9]', function () {
    $migrationsPath = dirname(__DIR__, 2).'/database/migrations';

    expect(filesWithForbiddenFloatTokens($migrationsPath))->toBe([]);
});

it('has no float or double tokens in app/Domain [C9]', function () {
    $domainPath = dirname(__DIR__, 2).'/app/Domain';

    if (! is_dir($domainPath)) {
        expect(true)->toBeTrue();

        return;
    }

    expect(filesWithForbiddenFloatTokens($domainPath))->toBe([]);
});
