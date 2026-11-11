<?php

// C14: la autorización se hace solo con Policies y permisos module.action.
// Prohibidos hasRole(), Gate::before y abort(403) en app/.

function filesWithForbiddenAuthTokens(string $directory): array
{
    $offenders = [];
    $forbidden = ['hasRole(', 'Gate::before(', 'abort(403)'];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach ($forbidden as $token) {
            if (str_contains($contents, $token)) {
                $offenders[] = $file->getPathname().' ('.$token.')';
            }
        }
    }

    return $offenders;
}

it('has no hasRole(), Gate::before or abort(403) in app/ [C14]', function () {
    $appPath = dirname(__DIR__, 2).'/app';

    expect(filesWithForbiddenAuthTokens($appPath))->toBe([]);
});
